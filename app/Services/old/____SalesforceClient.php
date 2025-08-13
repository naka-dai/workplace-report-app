<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SalesforceClient
{
    private Client $http;
    private string $loginUrl;
    private string $audience;
    private string $clientId;
    private string $username;
    private string $privateKeyPem;
    private string $apiVersion;
    private string $objectName;
    private int $timeout;

    public function __construct()
    {
        // .env から読み込み（必須）
        $this->loginUrl  = rtrim(env('SF_LOGIN_URL', ''), '/');
        $this->audience  = rtrim(env('SF_AUDIENCE', ''), '/');
        $this->clientId  = (string) env('SF_CLIENT_ID', '');
        $this->username  = (string) env('SF_USERNAME_SFDC', '');
        $this->apiVersion = (string) env('SF_API_VERSION', 'v61.0');
        $this->objectName = (string) env('SF_DEFECT_OBJECT', 'Claim__c'); // ← 今回は Claim__c
        $this->timeout    = (int) env('HTTP_TIMEOUT_SEC', 20);

        // 秘密鍵（Base64化したPEM）を復号
        $b64 = (string) env('SF_PRIVATE_KEY_B64', '');
        if ($b64 === '') {
            throw new \RuntimeException('SF_PRIVATE_KEY_B64 is empty');
        }
        $pem = base64_decode($b64, true);
        if ($pem === false || trim($pem) === '') {
            throw new \RuntimeException('SF_PRIVATE_KEY_B64 decode failed');
        }
        $this->privateKeyPem = $pem;

        // cURL の CA を php.ini で設定済みなら verify=true でOK
        // 環境によっては独自CAバンドルパスを指定したい場合があるので env 可
        $verify = env('CURL_CA_BUNDLE') ? env('CURL_CA_BUNDLE') : true;

        $this->http = new Client([
            'timeout' => $this->timeout,
            'verify'  => $verify, // 一時回避で false も可（本番NG）
        ]);
    }

    /**
     * Claim__c を作成し、必要に応じてファイルを添付してレコードIDを返す
     */
    public function createClaim(array $fields, ?array $file = null): string
    {
        $token = $this->getAccessToken();
        $instanceUrl = $token['instance_url'];
        $accessToken = $token['access_token'];

        // 1) sObject 生成
        $resp = $this->http->post($instanceUrl . "/services/data/{$this->apiVersion}/sobjects/{$this->objectName}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        if (!isset($data['id'])) {
            throw new \RuntimeException('Create record failed: ' . json_encode($data));
        }
        $recordId = $data['id'];

        // 2) 添付（任意）: ['filename' => string, 'mime' => string, 'contents' => binary|string]
        if ($file && isset($file['contents']) && $file['contents'] !== '') {
            $title = pathinfo($file['filename'] ?? 'upload', PATHINFO_FILENAME) ?: 'upload';
            $pathOnClient = $file['filename'] ?? 'upload.bin';
            $mime = $file['mime'] ?? 'application/octet-stream';
            $base64 = base64_encode(is_string($file['contents']) ? $file['contents'] : (string) $file['contents']);

            $payload = [
                'Title'                   => $title,
                'PathOnClient'            => $pathOnClient,
                'VersionData'             => $base64,
                'FirstPublishLocationId'  => $recordId,
            ];

            $this->http->post($instanceUrl . "/services/data/{$this->apiVersion}/sobjects/ContentVersion", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        }

        return $recordId;
    }

    /**
     * アクセストークン取得（JWTベアラーフロー）
     * @return array{access_token:string, instance_url:string, issued_at?:string, signature?:string, token_type?:string}
     */
    public function getAccessToken(): array
    {
        $jwt = $this->buildJwt();

        try {
            $resp = $this->http->post($this->loginUrl . '/services/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ],
            ]);
        } catch (RequestException $e) {
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            // デバッグ用に最低限の情報を投げる（秘密鍵やClientID全文は出さない）
            \Log::error('Salesforce token request failed', [
                'status' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'body'   => $body,
                'aud'    => $this->audience,
                'login'  => $this->loginUrl,
                'user'   => preg_replace('/^[^@]+/', '***', $this->username),
                'cid'    => substr($this->clientId, 0, 4) . '…' . substr($this->clientId, -4),
            ]);
            throw $e;
        }

        $data = json_decode((string) $resp->getBody(), true);
        if (!isset($data['access_token'], $data['instance_url'])) {
            throw new \RuntimeException('Salesforce token response invalid: ' . json_encode($data));
        }
        return $data;
    }

    /**
     * JWT を RS256 で署名して返す
     */
    private function buildJwt(): string
    {
        // クレーム
        $now = time();
        $claims = [
            'iss' => $this->clientId,    // Consumer Key
            'sub' => $this->username,    // ログインID
            'aud' => $this->audience,    // https://login.salesforce.com or https://test.salesforce.com
            'exp' => $now + 300,         // 5分以内
        ];

        // ヘッダ
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        // Base64URL エンコード
        $b64url = function ($data) {
            return rtrim(strtr(base64_encode(is_string($data) ? $data : json_encode($data)), '+/', '-_'), '=');
        };

        $segments = [
            $b64url($header),
            $b64url($claims),
        ];
        $signingInput = implode('.', $segments);

        // 署名
        $pkey = openssl_pkey_get_private($this->privateKeyPem);
        if (!$pkey) {
            throw new \RuntimeException('Invalid private key (PEM)');
        }
        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $pkey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($pkey);
        if (!$ok) {
            throw new \RuntimeException('JWT signing failed');
        }
        $segments[] = $b64url($signature);

        // デバッグ（安全な範囲のみ）
        \Log::info('JWT debug', [
            'iss' => substr($this->clientId, 0, 4) . '…' . substr($this->clientId, -4),
            'sub' => preg_replace('/^[^@]+/', '***', $this->username),
            'aud' => $this->audience,
            'exp' => gmdate('c', $claims['exp']),
        ]);

        return implode('.', $segments);
    }

public function submitClaimFromApp(array $app): string
    {
        // 0) sanitize: trim strings and normalize empties to null
        foreach (['severity','seiban','office_name','overview','occurred_at'] as $k) {
            if (array_key_exists($k, $app) && is_string($app[$k])) {
                $app[$k] = trim($app[$k]);
                if ($app[$k] === '') { $app[$k] = null; }
            }
        }

        // 1) datetime-local -> UTC ISO8601 (Z)
        $dt = null;
        if (!empty($app['occurred_at'])) {
            try {
                $dt = (new \DateTime($app['occurred_at'], new \DateTimeZone(date_default_timezone_get())))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $e) {
                $dt = null;
            }
        }

        // 2) severity 固定（軽/中/重 以外は受け付けない）
        $severity = $app['severity'] ?? null;
        if ($severity !== null) {
            // 全角・半角の正規化（必要最低限）
            if (function_exists('mb_convert_kana')) {
                $severity = mb_convert_kana($severity, 'as');
            }
            // 同義語 → 軽/中/重 へマッピング
            $syn = [
                'low' => '軽', 'light' => '軽', '軽度' => '軽',
                'medium' => '中', 'med' => '中', '中度' => '中',
                'high' => '重', 'heavy' => '重', '重度' => '重',
                '軽' => '軽', '中' => '中', '重' => '重',
            ];
            $key = is_string($severity) ? mb_strtolower($severity) : $severity;
            $severity = $syn[$key] ?? $severity;

            $allowedSeverity = ['軽','中','重'];
            if (!in_array($severity, $allowedSeverity, true)) {
                throw new \InvalidArgumentException('軽重レベルは「軽」「中」「重」のいずれかを指定してください。');
            }
        }

        $payload = array_filter([
            'ClaimOccurrenceDate__c' => $dt,
            'Lightweightlevel__c'    => $severity,
            'SEIBAN_TEXT__c'         => $app['seiban'] ?? null,
            'OfficeName__c'          => $app['office_name'] ?? null,
            'Overview__c'            => $app['overview'] ?? null,
        ], fn($v) => !is_null($v) && $v !== '');

        return $this->createClaim($payload);
    }
}
