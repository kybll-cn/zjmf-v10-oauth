<?php
/**
 * 微软 直连登录
 *
 * 直连 微软 官方 OAuth 2.0 接口,不经过第三方聚合服务
 *
 * @author  空雨不流泪
 * @link    https://www.kybll.cn/
 */

namespace oauth\oauth_microsoft;

use app\common\lib\Oauth;

class OauthMicrosoft extends Oauth
{
    public $info = [
        'name'        => 'OauthMicrosoft',
        'title'       => '微软',
        'description' => '微软 账号登录',
        'author'      => '空雨不流泪',
        'author_url'  => 'https://www.kybll.cn/',
        'help_url'    => 'https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
        'version'     => '1.0.0',
        'logo_url'    => 'microsoft.svg',
    ];


    /**
     * 授权地址
     */
    protected $authorizeUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    /**
     * 换取 access_token 地址
     */
    protected $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

    /**
     * 用户信息地址
     */
    protected $userInfoUrl = 'https://graph.microsoft.com/v1.0/me';

    /**
     * 时间 2026-09-27
     * @title 获取授权跳转地址
     * @desc  拼装 微软 授权地址,state 透传内核下发值
     * @author 空雨不流泪
     * @version v1
     * @param array $param - 插件配置 + callback + system_oauth_state
     * @return string 授权地址
     */
    public function url($param)
    {
        $keysArr = [
            'client_id'     => $param['client_id'],
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope'         => 'openid profile email User.Read',

            'redirect_uri' => $param['callback'],
            'state'        => $param['system_oauth_state'],
        ];

        return $this->authorizeUrl . '?' . http_build_query($keysArr, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 时间 2026-09-27
     * @title 授权回调
     * @desc  用 code 换取 access_token 并拉取用户信息
     * @author 空雨不流泪
     * @version v1
     * @param array $param - 回调参数 + 插件配置
     * @return array|string 成功返回 ['openid'=>..,'data'=>[..]],失败返回错误信息
     */
    public function callback($param)
    {
        if (empty($param['code'])) {
            return $param['error_description'] ?? ($param['error'] ?? '用户取消授权或授权失败');
        }

        $token = $this->accessToken($param);
        if (empty($token)) {
            return '获取 access_token 失败';
        }

        $user = $this->userInfo($token);
        if (empty($user['openid'])) {
            return $user['message'] ?? '获取用户信息失败';
        }

        return $user;
    }

    /**
     * 用 code 换取 access_token
     */
    protected function accessToken($param)
    {
        $body = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $param['client_id'],
            'client_secret' => $param['client_secret'],

            'code'          => $param['code'],
            'redirect_uri'  => $param['callback'],
        ];

        $res = $this->httpPost($this->tokenUrl, http_build_query($body, '', '&', PHP_QUERY_RFC3986), [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ]);

        $res = json_decode((string) $res, true);

        return is_array($res) ? ($res['access_token'] ?? '') : '';
    }

    /**
     * 拉取第三方用户信息
     *
     * @see https://learn.microsoft.com/graph/api/user-get
     */
    protected function userInfo($accessToken)
    {
        $raw = $this->httpGet($this->userInfoUrl, [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,

        ]);

        $res = json_decode((string) $raw, true);
        if (!is_array($res)) {
            return ['message' => '用户信息接口返回异常'];
        }

        # 用 id 作为唯一标识(个人微软账号与工作账号均稳定)
        if (empty($res['id'])) {
            return ['message' => (isset($res['error']['message']) ? $res['error']['message'] : '未获取到微软用户标识')];
        }

        return [
            'openid' => (string) $res['id'],
            'data'   => [
                'username' => $res['displayName'] ?? ($res['userPrincipalName'] ?? ''),
                'avatar'   => '',
                'address'  => $res['officeLocation'] ?? '',
            ],
        ];
    }

    /**
     * GET 请求
     */
    protected function httpGet($url, $headers = [])
    {
        return $this->request($url, null, $headers);
    }

    /**
     * POST 请求
     */
    protected function httpPost($url, $body, $headers = [])
    {
        return $this->request($url, $body, $headers);
    }

    /**
     * 统一请求入口(优先 curl,回落 file_get_contents)
     */
    protected function request($url, $body = null, $headers = [])
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $ret = curl_exec($ch);
            curl_close($ch);

            return $ret;
        }

        $ctx = [
            'http' => [
                'method'        => $body === null ? 'GET' : 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body === null ? '' : $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ];

        return @file_get_contents($url, false, stream_context_create($ctx));
    }
}
