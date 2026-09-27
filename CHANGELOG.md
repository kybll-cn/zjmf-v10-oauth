# 变更记录

本项目遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.0.0] - 2026-09-27

首个版本，12 个平台。

### 聚合登录组（走彩虹聚合登录）

- 新增 `rainbow_qq` —— QQ
- 新增 `rainbow_wx` —— 微信
- 新增 `rainbow_alipay` —— 支付宝
- 新增 `rainbow_sina` —— 微博
- 新增 `rainbow_baidu` —— 百度
- 新增 `rainbow_huawei` —— 华为
- 新增 `rainbow_xiaomi` —— 小米
- 新增 `rainbow_douyin` —— 抖音
- 新增 `rainbow_dingtalk` —— 钉钉

三个插件共用一对 AppID / AppKey；接口地址可配置，支持自建或其它聚合服务。

### 官方直连组

- 新增 `oauth_github` —— GitHub OAuth Apps
- 新增 `oauth_microsoft` —— 微软身份平台（Microsoft Graph）
- 新增 `oauth_google` —— Google OAuth 2.0 / OpenID Connect

均直连官方端点，不经过第三方聚合服务。

### 其它

- `build.py` —— 发行打包（全量 - 黑名单策略，自动补 `<目录名>/` 前缀）
- `tools/verify.py` —— 无 PHP 运行时的契约静态校验
