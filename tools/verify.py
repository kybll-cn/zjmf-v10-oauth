#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V10 三方登录（oauth）插件契约校验 —— 本机无 PHP 运行时，用静态方式把关

原理：对照 idcsmart V10 内核源码里三方登录的真实契约（app/home/model/OauthModel.php
      + app/common.php get_plugin_class() + config/idcsmart.php root_namespace）
      逐项核对插件源码，抓到「一装就断链」的错。

用法：
    python tools/verify.py            # 校验 plugins/ 下全部插件
    python tools/verify.py rainbow_qq # 只校验一个

@author 空雨不流泪
@link   https://www.kybll.cn/
"""

import os
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent.parent
PLUGINS_DIR = HERE / 'plugins'

# 内核传给插件 callback() 的请求参数名。
# OauthModel 里是 `$param = array_merge($param, $config)`，config 在后 —— 同名会覆盖请求参数。
REQUEST_PARAMS = {'name', 'callback', 'state', 'code', 'system_oauth_state', 'act'}

# 与上游参数同名但语义等价，仅提示
SHADOW_WARN = {'appid', 'appkey', 'client_id', 'client_secret', 'type', 'redirect_uri'}

INFO_REQUIRED = ['name', 'title', 'description', 'author', 'version']

RAINBOW_TYPES = {'qq', 'wx', 'alipay', 'sina', 'baidu', 'huawei', 'xiaomi', 'douyin', 'bilibili', 'dingtalk'}

errors, warnings, checked = [], [], []


def parse_name(name, ucfirst=True):
    """复刻 ThinkPHP parse_name($name, 1)"""
    name = re.sub(r'_([a-zA-Z])', lambda m: m.group(1).upper(), name)
    return name[0].upper() + name[1:] if ucfirst else name


def platforms():
    dirs = sorted(d for d in os.listdir(PLUGINS_DIR)
                  if (PLUGINS_DIR / d).is_dir() and not d.startswith('.'))
    if not dirs:
        sys.exit('plugins/ 下没有任何插件目录')
    return dirs


def check(d):
    path = PLUGINS_DIR / d
    files = sorted(f.name for f in path.iterdir() if f.is_file())

    mains = [f for f in files if f.endswith('.php') and f != 'config.php']
    if len(mains) != 1:
        errors.append('[%s] 主类文件应恰好 1 个，实际 %s' % (d, mains))
        return

    cls = mains[0][:-4]
    src = (path / mains[0]).read_text(encoding='utf-8')

    # ---- 1. 目录名 -> 类名（V10 用 parse_name($dir, 1) 推导，必须一致）
    expect = parse_name(d)
    if cls != expect:
        errors.append('[%s] 类名 %s != parse_name(dir,1) 得到的 %s' % (d, cls, expect))
    else:
        checked.append('[%s] 目录名→类名 %s' % (d, cls))

    # ---- 2. 命名空间
    m = re.search(r'namespace\s+([^;]+);', src)
    ns = m.group(1).strip() if m else ''
    if ns != 'oauth\\' + d:
        errors.append('[%s] 命名空间 %s != oauth\\%s' % (d, ns, d))
    else:
        checked.append('[%s] 命名空间 %s' % (d, ns))

    # ---- 3. 基类
    if not re.search(r'class\s+%s\s+extends\s+Oauth\b' % re.escape(cls), src):
        errors.append('[%s] 未继承 Oauth 基类' % d)
    else:
        checked.append('[%s] 继承 app\\common\\lib\\Oauth' % d)

    if not re.search(r'use\s+app' + re.escape('\\common\\lib\\Oauth') + r'\s*;', src):
        errors.append('[%s] 缺少 use app\\common\\lib\\Oauth' % d)
    else:
        checked.append('[%s] use app\\common\\lib\\Oauth' % d)

    # ---- 4. 必实现方法
    for meth in ('url', 'callback'):
        if not re.search(r'public\s+function\s+%s\s*\(' % meth, src):
            errors.append('[%s] 缺少方法 %s()' % (d, meth))
        else:
            checked.append('[%s] 实现 %s()' % (d, meth))

    # ---- 5. $info 必填键
    for key in INFO_REQUIRED:
        if not re.search(r"'%s'\s*=>" % key, src):
            errors.append('[%s] $info 缺少键 %s' % (d, key))
    checked.append('[%s] $info 键齐全' % d)

    m = re.search(r"'name'\s*=>\s*'([^']+)'", src)
    if m and m.group(1) != cls:
        errors.append("[%s] $info['name']=%s != 类名 %s" % (d, m.group(1), cls))
    else:
        checked.append("[%s] $info['name'] == %s" % (d, cls))

    # ---- 6. logo_url 文件真实存在 + SVG 结构
    m = re.search(r"'logo_url'\s*=>\s*'([^']+)'", src)
    if not m:
        errors.append('[%s] $info 缺少 logo_url' % d)
    else:
        logo = m.group(1)
        logo_path = path / logo
        if not logo_path.is_file():
            errors.append('[%s] logo_url 指向的 %s 不存在' % (d, logo))
        else:
            svg = logo_path.read_text(encoding='utf-8')
            if 'viewBox="0 0 1024 1024"' not in svg:
                errors.append('[%s] %s 的 viewBox 不是 1024x1024' % (d, logo))
            if svg.count('<path') == 0:
                errors.append('[%s] %s 没有任何图形' % (d, logo))
            if svg.count('<g') != svg.count('</g>'):
                errors.append('[%s] %s 的 <g> 标签不配对' % (d, logo))
            for arc in re.findall(r'\sd="([^"]*)"', svg):
                if arc.count('(') != arc.count(')'):
                    errors.append('[%s] %s 的 path 括号不配对' % (d, logo))
                    break
            checked.append('[%s] logo_url -> %s 存在且结构合法' % (d, logo))

    # ---- 7. 彩虹聚合登录：type 常量
    m = re.search(r"const\s+OAUTH_TYPE\s*=\s*'([^']+)'", src)
    if d.startswith('rainbow_'):
        if not m:
            errors.append('[%s] 缺少 OAUTH_TYPE 常量' % d)
        else:
            t = m.group(1)
            if t not in RAINBOW_TYPES:
                errors.append('[%s] OAUTH_TYPE=%s 不在彩虹支持列表' % (d, t))
            else:
                checked.append('[%s] 彩虹 type=%s' % (d, t))
        # 必须透传 state
        if 'system_oauth_state' not in src:
            errors.append('[%s] 未透传内核的 system_oauth_state' % d)
        else:
            checked.append('[%s] 透传 system_oauth_state' % d)

    # ---- 7b. 直连插件：必须有官方端点，且必须透传 state
    if d.startswith('oauth_'):
        for prop in ('authorizeUrl', 'tokenUrl', 'userInfoUrl'):
            if not re.search(r'protected\s+\$' + prop + r"\s*=\s*'(https://[^']+)'", src):
                errors.append('[%s] 缺少官方端点 $%s' % (d, prop))
        checked.append('[%s] 官方端点齐备' % d)
        if 'system_oauth_state' not in src:
            errors.append('[%s] 未透传内核的 system_oauth_state' % d)
        else:
            checked.append('[%s] 透传 system_oauth_state' % d)

    # ---- 8. config.php
    cfg_path = path / 'config.php'
    if not cfg_path.is_file():
        errors.append('[%s] 缺少 config.php' % d)
        return

    cfg = cfg_path.read_text(encoding='utf-8')
    if not re.search(r'return\s*\[', cfg):
        errors.append('[%s] config.php 没有 return 数组' % d)
        return

    keys = re.findall(r"^\s{4}'([a-zA-Z_][a-zA-Z0-9_]*)'\s*=>\s*\[", cfg, re.M)
    if not keys:
        errors.append('[%s] config.php 未解析到配置项' % d)
    for k in keys:
        if k in REQUEST_PARAMS:
            errors.append("[%s] config 键 '%s' 会覆盖内核请求参数" % (d, k))
        elif k in SHADOW_WARN:
            warnings.append("[%s] config 键 '%s' 与上游参数同名（语义等价，无影响）" % (d, k))
        if not re.search(r"'%s'\s*=>\s*\[[^\]]*?'value'\s*=>" % k, cfg, re.S):
            errors.append("[%s] config 项 '%s' 缺少 value 键" % (d, k))
        blk = re.search(r"'%s'\s*=>\s*\[(.*?)\n    \]" % k, cfg, re.S)
        if blk:
            for field in ('title', 'type', 'value', 'tip'):
                if "'%s'" % field not in blk.group(1):
                    warnings.append("[%s] config 项 '%s' 缺少 %s" % (d, k, field))
    checked.append('[%s] config 键 %s 合规' % (d, keys))


def main():
    dirs = platforms()
    if len(sys.argv) > 1:
        dirs = [a for a in sys.argv[1:] if a in dirs] or dirs

    for d in dirs:
        check(Path(d).name)

    print('=' * 64)
    print('V10 三方登录（oauth）插件契约校验')
    print('=' * 64)
    print('\n检查插件: %d 个 — %s\n' % (len(dirs), ', '.join(dirs)))
    print('--- 通过 (%d) ---' % len(checked))
    for c in checked:
        print('  OK   ' + c)
    print('\n--- 警告 (%d) ---' % len(warnings))
    for w in warnings:
        print('  WARN ' + w)
    print('\n--- 错误 (%d) ---' % len(errors))
    for e in errors:
        print('  FAIL ' + e)
    print('\n结论: %s' % ('全部通过' if not errors else '存在 %d 项错误' % len(errors)))
    return 1 if errors else 0


if __name__ == '__main__':
    sys.exit(main())
