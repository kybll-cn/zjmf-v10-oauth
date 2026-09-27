<?php
/**
 * Google 直连登录配置
 *
 * 配置键名不能使用 name/callback/state/code/system_oauth_state 等内核参数
 *
 * @author  空雨不流泪
 * @link    https://www.kybll.cn/
 */

return [
    'client_id' => [
        'title' => 'Client ID',
        'type'  => 'text',
        'value' => '',
        'tip'   => 'Google Cloud 控制台 OAuth 2.0 客户端 ID',
    ],
    'client_secret' => [
        'title' => 'Client Secret',
        'type'  => 'text',
        'value' => '',
        'tip'   => 'Google Cloud 控制台 OAuth 2.0 客户端密钥',
    ],
];
