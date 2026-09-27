<?php
/**
 * 彩虹聚合登录 - QQ
 *
 * 通过彩虹聚合登录（blogcloud.cn）接入 QQ 授权登录
 *
 * @author  空雨不流泪
 * @link    https://www.kybll.cn/
 * @see     https://login.blogcloud.cn/doc
 * @version 1.0.0
 */

namespace oauth\rainbow_qq;

use app\common\lib\Oauth;

class RainbowQq extends Oauth
{
    /**
     * 彩虹聚合登录的平台标识
     */
    const OAUTH_TYPE = 'qq';

    /**
     * 默认接口地址
     */
    const DEFAULT_API_URL = 'https://login.blogcloud.cn/';

    public $info = [
        'name'        => 'RainbowQq',
        'title'       => 'QQ',
        'description' => '彩虹聚合登录 - QQ',
        'author'      => '空雨不流泪',
        'author_url'  => 'https://www.kybll.cn/',
        'help_url'    => 'https://login.blogcloud.cn/doc',
        'version'     => '1.0.0',
        'logo_url'    => 'qq.svg',
    ];

    /**
     * 时间 2026-09-27
     * @title 获取授权跳转地址
     * @desc  请求彩虹聚合登录获取上游授权地址,由内核重定向
     * @author 空雨不流泪
     * @version v1
     * @param array $params - 插件配置 + callback + system_oauth_state
     * @return string 授权地址
     */
    public function url($params)
    {
        $result = $this->apiGet($this->apiUrl($params), [
            'act'          => 'login',
            'appid'        => $params['appid'] ?? '',
            'appkey'       => $params['appkey'] ?? '',
            'type'         => self::OAUTH_TYPE,
            'redirect_uri' => $params['callback'] ?? '',
            'state'        => $params['system_oauth_state'] ?? '',
        ]);

        if (!$this->isSuccess($result) || empty($result['url'])) {
            $this->logError('获取授权地址失败: ' . ($result['msg'] ?? '接口无响应'));

            # 失败时回登录页,避免前端跳转到非法地址
            return $this->backToLogin($params);
        }

        return $result['url'];
    }

    /**
     * 时间 2026-09-27
     * @title 授权回调
     * @desc  用 code 换取第三方用户信息,openid 为彩虹返回的 social_uid
     * @author 空雨不流泪
     * @version v1
     * @param array $params - 回调参数 + 插件配置
     * @return array|string 成功返回 ['openid'=>..,'data'=>[..]],失败返回错误信息
     */
    public function callback($params)
    {
        if (empty($params['code'])) {
            return $params['msg'] ?? '用户取消授权或授权失败';
        }

        $result = $this->apiGet($this->apiUrl($params), [
            'act'    => 'callback',
            'appid'  => $params['appid'] ?? '',
            'appkey' => $params['appkey'] ?? '',
            'type'   => self::OAUTH_TYPE,
            'code'   => $params['code'],
        ]);

        if (!$this->isSuccess($result)) {
            return $result['msg'] ?? '获取用户信息失败';
        }
        if (empty($result['social_uid'])) {
            return '未获取到第三方用户标识 social_uid';
        }

        return [
            'openid' => $result['social_uid'],
            'data'   => [
                'username' => $result['nickname'] ?? '',
                'avatar'   => $result['faceimg'] ?? '',
                'gender'   => $result['gender'] ?? '',
                'address'  => $result['location'] ?? '',
                'ip'       => $result['ip'] ?? '',
            ],
        ];
    }

    /**
     * 接口地址,允许配置自建/第三方聚合服务
     */
    protected function apiUrl($params)
    {
        $base = !empty($params['apiurl']) ? $params['apiurl'] : self::DEFAULT_API_URL;

        return rtrim($base, '/') . '/connect.php';
    }

    /**
     * 请求彩虹聚合登录接口,返回解析后的数组
     */
    protected function apiGet($url, $query)
    {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $response = '';
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36');
            $response = curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $response = @file_get_contents($url);
        }

        $result = json_decode((string) $response, true);

        return is_array($result) ? $result : [];
    }

    /**
     * 判断接口是否成功(code 为 0)
     */
    protected function isSuccess($result)
    {
        return isset($result['code']) && intval($result['code']) === 0;
    }

    /**
     * 出错时回跳本站登录页
     */
    protected function backToLogin($params)
    {
        $parts = parse_url($params['callback'] ?? '');

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '/login.htm';
        }

        $domain = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $domain .= ':' . $parts['port'];
        }

        return $domain . '/login.htm';
    }

    /**
     * 写入错误日志
     */
    protected function logError($message)
    {
        if (function_exists('trace')) {
            trace('[oauth:' . self::OAUTH_TYPE . '] ' . $message, 'error');
        }
    }
}
