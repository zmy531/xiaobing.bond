#!/bin/bash

echo "======================================"
echo "  Zmy 个人主页后端一键部署脚本"
echo "======================================"
echo ""

INSTALL_DIR="/www/server/xiaobing-server"
NODE_VERSION="18"

echo "[1/5] 检查 Node.js 环境..."
if command -v node &> /dev/null && command -v npm &> /dev/null; then
    echo "  ✓ Node.js 已安装: $(node --version)"
    echo "  ✓ npm 已安装: $(npm --version)"
else
    echo "  ✗ Node.js 未安装，开始安装..."
    
    if command -v apt &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
        apt-get install -y nodejs
    elif command -v yum &> /dev/null; then
        curl -fsSL https://rpm.nodesource.com/setup_${NODE_VERSION}.x | bash -
        yum install -y nodejs
    else
        echo "  ✗ 无法识别包管理器，请手动安装 Node.js 18+"
        exit 1
    fi
    
    echo "  ✓ Node.js 安装完成"
fi

echo ""
echo "[2/5] 创建项目目录..."
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR
echo "  ✓ 目录: $INSTALL_DIR"

echo ""
echo "[3/5] 安装依赖..."
npm install express cors multer jsonwebtoken fs path
echo "  ✓ 依赖安装完成"

echo ""
echo "[4/5] 创建配置文件..."
cat > .env << EOF
ADMIN_USER=admin
ADMIN_PASS=admin123
JWT_SECRET=zmy_admin_secret_key_2026
PORT=3000
EOF
echo "  ✓ 配置文件创建完成"

echo ""
echo "[5/5] 启动服务..."

echo "  方式一: 使用 nohup 后台运行（推荐）"
echo "  nohup node server.js > /var/log/xiaobing-server.log 2>&1 &"
echo ""
echo "  方式二: 使用 PM2 运行（需先安装 pm2）"
echo "  npm install -g pm2"
echo "  pm2 start server.js --name xiaobing-backend"
echo ""

echo "======================================"
echo "  部署完成!"
echo "======================================"
echo ""
echo "  测试命令:"
echo "  curl http://127.0.0.1:3000/api/health"
echo ""
echo "  应该返回: {\"status\":\"ok\"}"
echo ""
echo "  如果返回成功，请继续配置宝塔反向代理"
