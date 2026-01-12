<?php

namespace App\Services;

class CmiService
{
    private $storeKey;
    private $config;

    public function __construct()
    {
        $this->config = config('cmi');
        $this->storeKey = $this->config['store_key'];
    }

    public function generateHash(array $params): string
    {
        $postParams = array();
        foreach ($params as $key => $value) {
            array_push($postParams, $key);
        }

        natcasesort($postParams);
        $hashval = '';
        foreach ($postParams as $param) {
            $paramValue = trim($params[$param]);
            $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

            $lowerParam = strtolower($param);
            if ($lowerParam != "hash" && $lowerParam != "encoding") {
                $hashval .= $escapedParamValue . '|';
            }
        }

        $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $this->storeKey));
        $hashval .= $escapedStoreKey;

        $calculatedHashValue = hash('sha512', $hashval);
        return base64_encode(pack('H*', $calculatedHashValue));
    }
    public function generateCallbackHash(array $params): string
    {
        $postParams = array();
        foreach ($params as $key => $value) {
            array_push($postParams, $key);
        }

        natcasesort($postParams);
        $hashval = '';
        foreach ($postParams as $param) {
            $paramValue = html_entity_decode(preg_replace("/\n$/", "", $params[$param]), ENT_QUOTES, 'UTF-8');
            $escapedParamValue = str_replace("|", "\\|", str_replace("\\", "\\\\", $paramValue));

            $lowerParam = strtolower($param);
            if ($lowerParam != "hash" && $lowerParam != "encoding") {
                $hashval .= $escapedParamValue . '|';
            }
        }

        $escapedStoreKey = str_replace("|", "\\|", str_replace("\\", "\\\\", $this->storeKey));
        $hashval .= $escapedStoreKey;

        $calculatedHashValue = hash('sha512', $hashval);
        return base64_encode(pack('H*', $calculatedHashValue));
    }

    public function preparePaymentParams(array $orderData): array
    {
        $params = [
            'clientid' => $this->config['client_id'],
            'storetype' => '3d_pay_hosting',
            'trantype' => 'PreAuth',
            'amount' => number_format($orderData['amount'] ?? 0, 2, '.', ''),
            'currency' => "504",
            'oid' => $orderData['oid'],
            'okUrl' => $this->config['ok_fail_url'].'/'.$orderData['oid'].'/'.$this->config['ok_url'],
            'failUrl' => $this->config['ok_fail_url'].'/'.$orderData['oid'].'/'.$this->config['fail_url'],
            'lang' => $this->config['default_lang'],
            'email' => $orderData['email'] ?? '',
            'BillToName' => $orderData['name'] ?? '',
            'BillToCompany' => $orderData['billToCompany'] ?? '',
            'BillToStreet1' => $orderData['billToStreet1'] ?? '',
            'BillToCity' => $orderData['billToCity'] ?? '',
            'BillToStateProv' => $orderData['billToStateProv'] ?? '',
            'BillToPostalCode' => $orderData['billToPostalCode'] ?? '',
            'tel' => $orderData['tel'] ?? '',
            'rnd' => microtime(),
            'hashAlgorithm' => 'ver3',
            'callbackUrl' => route('payment.callback'),
            'encoding' => $this->config['encoding'],
            'CallbackResponse' => 'true',

        ];

        $params['hash'] = $this->generateHash($params);
        return $params;
    }
} 