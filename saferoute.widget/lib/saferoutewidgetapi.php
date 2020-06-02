<?php

namespace Saferoute\Widget;

/**
 * API-������ �������� SafeRoute
 *
 * @version 2.0
 */
class SafeRouteWidgetApi
{
    /**
     * @var string ����� �����������
     */
    private $token;

    /**
     * @var string|int ID ��������
     */
    private $shopId;

    /**
     * @var array ������ �������
     */
    private $data;

    /**
     * @var string HTTP-����� �������
     */
    private $method = 'POST';

    /**
     * ���������� IP-����� ������������
     *
     * @return string IP-�����
     */
    private function getClientIP()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            return $_SERVER['HTTP_X_FORWARDED_FOR'];

        if (!empty($_SERVER['HTTP_CLIENT_IP']))
            return $_SERVER['HTTP_CLIENT_IP'];

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $url URL �������
     * @return bool
     */
    private function isHtmlRequest($url)
    {
        preg_match("/\.html$/", $url, $m);
        return (bool) $m;
    }


    /**
     * @param string $token ����� �����������
     * @param string|int $shopId ID ��������
     */
    public function __construct($token = null, $shopId = null)
    {
        $this->setToken($token);
        $this->setShopId($shopId);
    }

    /**
     * ������ ������ �����������
     *
     * @param string $token ����� �����������
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * ������ ID ��������
     *
     * @param string|int $shopId ID ��������
     */
    public function setShopId($shopId)
    {
        $this->shopId = $shopId;
    }

    /**
     * ������ ������ �������
     *
     * @param array $data ������ �������
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * ������ ������ �������
     *
     * @param string $method ����� �������
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * ���������� ������
     *
     * @param string $url URL
     * @param array $headers �������������� ��������� �������
     * @return string
     */
    public function submit($url, $headers = [])
    {
        // �������� ���� �������
        if ($this->isHtmlRequest($url)) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);
            curl_close($curl);

            return $response;
        // ������ � API
        } else {
            $headers[] = 'Content-Type:application/json';
            $headers[] = "Authorization:Bearer $this->token";
            $headers[] = "shop-id:$this->shopId";
            $headers = array_unique($headers);

            if (isset($this->data['ip']) && !$this->data['ip']) {
                $ip = $this->getClientIP();
                if ($ip !== '::1' && $ip !== '127.0.0.1') $this->data['ip'] = $ip;
            }

            if ($this->method === 'GET')
                $url .= '?' . http_build_query($this->data);

            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method);

            if ($this->method === 'POST' || $this->method === 'PUT')
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->data));

            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            $response = json_decode(curl_exec($curl));
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($status === 200)
                return json_encode(['status' => $status, 'data' => $response]);

            return json_encode([
                'status' => $status,
                'code' => isset($response->code) ? $response->code : null,
            ]);
        }
    }
}
