<?php

namespace Saferoute\Widget;

/**
 * API-������ �������
 */
class WidgetApi
{
    /**
     * @var string API-���� ��������
     */
    private $apiKey;

    /**
     * @var array ������ �������
     */
    private $data;

    /**
     * @var string HTTP-����� �������
     */
    private $method = 'POST';



    /**
     * @param string $apiKey API-���� ��������
     */
    public function __construct($apiKey = '')
    {
        if ($apiKey) $this->setApiKey($apiKey);
    }

    /**
     * ������ API-�����.
     *
     * @param string $apiKey API-���� ��������
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * ������ ������ �������.
     *
     * @param array $data ������ �������
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * ������ ������ �������.
     *
     * @param string $method ����� �������
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * ���������� ������.
     *
     * @param string $url URL
     * @param array $headers �������������� ��������� �������
     * @return string
     */
    public function submit($url, $headers = [])
    {
        $url = preg_replace('/:key/', $this->apiKey, $url);
        
        if ($this->method === 'GET')
            $url .= '?' . http_build_query($this->data);
        
        $curl = curl_init($url);
        
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method);
        
        if ($this->method === 'POST' || $this->method === 'PUT')
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->data));
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(['Content-Type:application/json'], $headers));
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return $response;
    }
}