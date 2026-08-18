# ✨ 个人主页系统 · Personal Homepage
在线预览请访问：https://xiaobing.bond
部署教程查看https://xiaobing.bond/about.html
一个功能丰富、界面美观的**一站式个人主页源码**，包含首页 / 商城 / 相册 / 软件库 / 工具查询 / 留言板 / AI 助手 / 粒子特效 / 联系页面 等多个模块，开箱即用，无需数据库，JSON 文件本地存储。

---

## 🎨 界面特色

- **玻璃拟态（Glassmorphism）** 风格设计，多主题切换
- 动态粒子背景，可与访客互动的 3D 粒子墙
- 响应式布局，适配 PC / Pad / 手机
- 所有图标、默认图、字体、SVG 均已内置，无需额外联网加载

---

## 🧩 功能模块

| 模块 | 说明 |
|---|---|
| 🏠 **首页** | 个人介绍、联系卡片、公告栏、技能、项目入口，可自定义头像/背景图 |
| 🛒 **商城** | 商品发布、订单管理、卡密自动发货、微信/支付宝收款码、购物流程 |
| 📷 **相册** | 照片墙展示，支持旋转切换、多种过渡特效 |
| 📦 **软件库** | 软件发布、版本管理、下载次数统计、卡密授权机制 |
| 🛠 **工具箱** | 手机号归属地、QQ 信息查询、IP 详情、身份证查询、抖音解析等 |
| 💬 **留言板** | 访客可留言、展示访客统计、访客 IP 归属地 |
| 🤖 **AI 助手** | 本地问答库模式 + 兼容 OpenAI 接口的大模型模式，可自由切换 |
| 🎮 **小游戏** | 3D 粒子互动、贪吃蛇等内置游戏 |
| 📞 **联系页** | 默认联系方式卡片，也可**上传自定义 HTML 完全替换**整个页面 |
| ⚙️ **后台管理** | 登录后可配置：个人信息 / 商城 / 联系 / 照片 / 软件 / AI / 背景 等所有内容 |

---

## ⚡ 快速开始

### 默认后台账号
```
用户名：admin
密码：  admin
```
> 🔴 **部署后请立即登录 `admin.html` 修改密码！** （在 `api/login.php` 顶部修改 `ADMIN_USER`、`ADMIN_PASS`、`JWT_SECRET`）

### 环境要求
- PHP **7.4 及以上**（推荐 8.0 / 8.1，无需任何扩展）
- 目录写入权限（`data/`、`uploads/` 目录）
- 无需 MySQL 数据库（全部使用 JSON 本地存储）

### 方案一：本地快速运行（推荐先本地预览）
```bash
# 进入项目根目录
cd personal-homepage

# 使用 PHP 内置服务器启动
php -S 127.0.0.1:8080 -t .
```
然后浏览器访问 `http://127.0.0.1:8080` 即可。

### 方案二：宝塔面板部署
详见项目根目录下的 **[deploy.html](./deploy.html)** 部署教程页面（打开即可看到图文步骤）。

### 方案三：虚拟主机 / 任意 PHP 空间
1. 把整个项目上传到网站根目录
2. 确保 `data/`、`uploads/` 目录可写（权限 755 或 777）
3. 访问 `你的域名/admin.html`，使用 `admin / admin` 登录配置即可

---

## 📁 目录结构

```
├─ admin.html             后台管理入口
├─ index.html             前台首页
├─ shop.html              商城页面
├─ photos.html            相册页面
├─ software.html          软件库页面
├─ tools.html             工具箱页面
├─ message.html           留言板
├─ contact.html           联系页面（可上传HTML替换）
├─ game.html / particle3d.html   小游戏/粒子特效
├─ about.html             项目介绍
├─ deploy.html            部署教程（推荐打开）
├─ login.html             后台登录
├─ 404.html               404 页面
│
├─ api/                   所有后端 PHP 接口（纯 JSON，纯 PHP 原生）
├─ data/                  数据存储目录（JSON 文件，保留商品和软件）
├─ uploads/               用户上传资源
│   └─ shop/              ★ 商品图片（保留）
│   ├─ avatar/            头像（可忽略）
│   ├─ background/        背景图（可忽略）
│   ├─ pay_qr/            收款码（可忽略）
│   └─ photos/            相册图（可忽略）
├─ music/                 首页播放音乐（可替换 / 可忽略）
└─ static/                ★ 静态资源（图标/默认图/字体/CSS/JS）全部保留
```

---

## ⚠️ 上传到 GitHub 前的注意事项

仓库自带一份 `.gitignore`，已经自动忽略你的**个人上传**（头像、收款码、背景、相册、音乐等），只保留：
- ✅ 源码（所有 `.html`、`api/*.php`、`static/*`、`.gitignore`、`README.md`、`deploy.html`、`about.html`）
- ✅ 商品数据（`data/shop/products.json`）和商品图片（`uploads/shop/*`）
- ✅ 软件列表（`data/software.json`）
- ⚠️ 软件下载的二进制文件（`data/software_files/*`）默认忽略，如果要分享给他人可自行上传。

---

## 🛠 自定义小提示

1. **联系页面支持上传完整 HTML**：后台 `联系设置` Tab 底部上传 `.html` 文件即可 100% 原样替换 contact.html。
2. **修改顶部 JWT 密钥**：`api/login.php` 顶部的 `JWT_SECRET` 上线前务必改成随机字符串。
3. **替换 logo / favicon**：直接覆盖 `static/img/logo.png` 和 `static/img/favicon.ico` 即可。
4. **替换首页音乐**：把 `.mp3` + `.lrc` 同名文件扔到 `music/` 目录即可，打开首页自动播放。

---

## 💡 免责声明

- 本项目仅供学习交流，请遵守当地法律法规，不得用于非法用途。
- 工具箱中涉及的手机号、IP、身份证、QQ 等查询功能，仅用于演示接口集成，请替换为你自己的合规数据源。
- AI 接口默认为兼容 OpenAI 格式的代理接口，请替换为自己的 API Key。
- **因使用本项目产生的一切法律责任由使用者自行承担。**

投喂
<img width="1171" height="1171" alt="617081175-f0fe117c-0df5-454b-a6d0-171c1dd3c10b" src="https://github.com/user-attachments/assets/e2d403a0-3e97-4230-b76c-246bb569857d" />

---

<div align="center">
  <b>Made with ❤️</b> · 如果喜欢，欢迎给个 Star ⭐
</div>
