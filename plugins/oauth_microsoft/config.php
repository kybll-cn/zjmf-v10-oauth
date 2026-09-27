<?php
/**
 * 微软 直连登录配置
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
        'tip'   => 'Azure 应用注册的应用程序(客户端)ID',
    ],
    'client_secret' => [
        'title' => 'Client Secret',
        'type'  => 'text',
        'value' => '',
        'tip'   => 'Azure 应用注册的客户端密码',
    ],
];
