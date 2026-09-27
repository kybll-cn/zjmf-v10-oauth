# 智简魔方 V10 三方登录插件集

给 **智简魔方（idcsmart）V10 业务系统**用的三方登录（`oauth`）插件集，共 **12 个平台**，分两类：

- **聚合登录组（9 个）** —— 走[彩虹聚合登录](https://login.blogcloud.cn/doc)，平台多、一次配置全站通用
- **官方直连组（3 个）** —— 直连 GitHub / 微软 / Google 官方 OAuth 2.0，不经过第三方

> 作者：**空雨不流泪** · <https://www.kybll.cn/> · 许可 GPL-3.0

---

## 一、插件清单

### 聚合登录组（彩虹）

| 目录名 | 类名 | 平台 | 彩虹 `type` |
|---|---|---|---|
| `rainbow_qq` | `RainbowQq` | QQ | `qq` |
| `rainbow_wx` | `RainbowWx` | 微信 | `wx` |
| `rainbow_alipay` | `RainbowAlipay` | 支付宝 | `alipay` |
| `rainbow_sina` | `RainbowSina` | 微博 | `sina` |
| `rainbow_baidu` | `RainbowBaidu` | 百度 | `baidu` |
| `rainbow_huawei` | `RainbowHuawei` | 华为 | `huawei` |
| `rainbow_xiaomi` | `RainbowXiaomi` | 小米 | `xiaomi` |
| `rainbow_douyin` | `RainbowDouyin` | 抖音 | `douyin` |
| `rainbow_dingtalk` | `RainbowDingtalk` | 钉钉 | `dingtalk` |

### 官方直连组

| 目录名 | 类名 | 平台 | 申请入口 |
|---|---|---|---|
| `oauth_github` | `OauthGithub` | GitHub | <https://github.com/settings/developers> |
| `oauth_microsoft` | `OauthMicrosoft` | 微软 | <https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade> |
| `oauth_google` | `OauthGoogle` | Google | <https://console.cloud.google.com/apis/credentials> |

---

## 二、安装

### 方式一：用安装包

从 [Releases](https://github.com/kybll-cn/zjmf-v10-oauth/releases) 下载对应平台的 zip，解压后把整个
`<目录名>/` 放进站点：

```
站点根/public/plugins/oauth/<目录名>/
```

然后到后台 **系统 → 接口管理 → 三方登录接口**，刷新列表，点安装、填配置、启用。

> 一个平台 = 一个插件目录。想让登录页同时出现 QQ 和微信，就装两个。

### 方式二：从源码打包

```bash
python build.py                 # 打包全部 12 个平台到 dist/
python build.py --plugin rainbow_qq
```

---

## 三、配置

### 聚合登录组（彩虹）

去[彩虹聚合登录用户中心](https://login.blogcloud.cn/user/)注册账号 → 创建应用，拿到 **AppID / AppKey**，
**三个插件填同一对即可**（一个应用对应全平台）。

| 配置项 | 说明 |
|---|---|
| AppID | 彩虹聚合登录用户中心创建应用后获取 |
| AppKey | 同上 |
| 接口地址 | 默认 `https://login.blogcloud.cn/`，用自建或其它聚合服务时改这里 |

**回调地址**不用手填 —— 插件会自动带上，形如：

```
https://你的域名/console/v1/oauth/callback/rainbow_qq
```

### 官方直连组

各组分别在对应平台创建 OAuth 应用，客户端 ID / 密钥填进插件配置：

| 平台 | 配置项 | 需要填的回调地址 |
|---|---|---|
| GitHub | Client ID / Client Secret | `https://你的域名/console/v1/oauth/callback/oauth_github` |
| 微软 | Client ID / Client Secret | `https://你的域名/console/v1/oauth/callback/oauth_microsoft` |
| Google | Client ID / Client Secret | `https://你的域名/console/v1/oauth/callback/oauth_google` |

注意事项：

- **GitHub**：Authorization callback URL 填上面的地址，scope 用 `read:user user:email`
- **微软**：Azure 应用注册里，账户类型选「任何组织目录中的账户和个人 Microsoft 账户」；
  重定向 URI 类型选 **Web**，填上面的地址
- **Google**：OAuth 同意屏幕先发布，凭据类型选「Web 应用」，已获授权的重定向 URI 填上面的地址

---

## 四、开发约定（改动前必读）

### 命名四一致

V10 靠目录名推导类名，四者必须完全一致，否则插件**装不上或加载不到**：

```
目录名 rainbow_qq
  → 类名      RainbowQq          （parse_name(目录名, 1)）
  → $info['name'] = 'RainbowQq'  （必须等于类名）
  → 命名空间  oauth\rainbow_qq
```

> 顺带一提：`ucwords('rainbow_qq')` 实际得到的是 `Rainbow_qq`，靠自动加载大小写不敏感也能跑通，
> 但**规定写法仍是 `RainbowQq`**，别赌。

### 方法契约

主类继承 `app\common\lib\Oauth`，只需实现两个方法（`install()` / `uninstall()` 基类已实现）：

| 方法 | 返回 |
|---|---|
| `url($param)` | 授权地址**字符串** |
| `callback($param)` | **数组** `['openid' => '唯一标识', 'data' => [...]]`；返回字符串则被当作错误信息 |

- `openid` 不可为空，内核强校验（空则报 `openid not found`）。
- `data` 支持 `username` / `avatar` / `gender` / `address` / `ip`，会被整包存进 `idcsmart_oauth.oauth`。

### ⚠️ state 必须透传

内核会拿 `$_SESSION['system_oauth_state']` 校验回跳 URL 上的 `state`，对不上直接 400。
所以 `url()` 里**必须**把内核下发的值原样传给上游：

```php
'state' => $param['system_oauth_state'],
```

自己造 state、或不传 state 的上游（回跳不带 state），**都装不上 V10** —— 选型时先验证这点。

### ⚠️ config 键的覆盖陷阱

内核的调用是 `$param = array_merge($param, $config)`，**config 在后**。所以
`config.php` 里**严禁**出现这六个键，否则会覆盖内核参数导致流程崩：

```
name / callback / state / code / system_oauth_state / act
```

`appid` / `appkey` / `client_id` / `client_secret` 与上游参数同名，但语义等价，**无害**。

---

## 五、本地校验（无需 PHP）

本仓库带静态校验脚本，改完代码先跑一遍：

```bash
python tools/verify.py            # 校验全部插件
python tools/verify.py rainbow_qq # 校验单个
```

逐项核对：目录名/类名/命名空间四一致、继承与方法、`$info` 必填键、`logo_url` 文件存在性、
SVG 结构、`state` 透传、config 键冲突。退出码非 0 表示有错。

> 这是**结构校验**，不是语法检查 —— 它抓不到 `$a ?? $b ?: $c` 这类语义问题，但有 PHP 环境时请再跑 `php -l`。

---

## 六、目录结构

```
.
├── build.py              打包脚本（全量 - 黑名单策略）
├── tools/
│   └── verify.py         契约校验脚本
├── plugins/              各平台插件源码（目录名 = 安装目录名）
│   ├── rainbow_qq/
│   │   ├── RainbowQq.php
│   │   ├── config.php
│   │   └── qq.svg
│   └── ...
└── dist/                 打包产物（不进版本库）
```

---

## 七、支持范围说明

聚合登录组支持哪些平台，**取决于你的彩虹服务端部署**，客户端只能按 `type` 探测。
本仓库按[官方文档](https://login.blogcloud.cn/doc)声明的平台实现；若你的部署还开启了
`bilibili` 或其它平台，照 `rainbow_qq` 复制一份改三处（目录名 / 类名 / `OAUTH_TYPE`）即可。

---

## License

GPL-3.0，见 [LICENSE](LICENSE)。
