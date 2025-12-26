<?php
namespace Api\Helpers;

class ExternalService {
    public static function requestJson($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init($url);
        
        // Default headers
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Merge headers
        $finalHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error,
                'data' => null
            ];
        }
        
        $decoded = json_decode($response, true);
        
        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300),
            'status' => $httpCode,
            'error' => ($httpCode >= 400) ? ($decoded['message'] ?? 'HTTP Error ' . $httpCode) : null,
            'data' => $decoded ?? $response // Return raw response if not JSON
        ];
    }
}
