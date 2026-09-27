<?php
/**
 * GitHub 直连登录配置
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
        'tip'   => 'GitHub 开发者设置创建 OAuth App 后获取',
    ],
    'client_secret' => [
        'title' => 'Client Secret',
        'type'  => 'text',
        'value' => '',
        'tip'   => 'GitHub OAuth App 的 Client Secret,申请地址见插件帮助链接',
    ],
];
