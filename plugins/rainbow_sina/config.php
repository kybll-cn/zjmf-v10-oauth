<?php
/**
 * 彩虹聚合登录 - 微博 配置
 *
 * 配置键名不能使用 name/callback/state/code/system_oauth_state 等内核参数
 *
 * @author  空雨不流泪
 * @link    https://www.kybll.cn/
 * @see     https://login.blogcloud.cn/doc
 */

return [
    'appid' => [
        'title' => 'AppID',
        'type'  => 'text',
        'value' => '',
        'tip'   => '彩虹聚合登录用户中心创建应用后获取',
    ],
    'appkey' => [
        'title' => 'AppKey',
        'type'  => 'text',
        'value' => '',
        'tip'   => '彩虹聚合登录用户中心创建应用后获取',
    ],
    'apiurl' => [
        'title' => '接口地址',
        'type'  => 'text',
        'value' => 'https://login.blogcloud.cn/',
        'tip'   => '默认 https://login.blogcloud.cn/ ，使用自建或其它聚合服务时修改',
    ],
];
