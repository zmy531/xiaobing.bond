const express = require('express');
const multer = require('multer');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');
const fs = require('fs');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;
const JWT_SECRET = process.env.JWT_SECRET || 'xiaobing-bond-secret-key-2026';
const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';

// ============ 数据存储 ============
const DATA_DIR = path.join(__dirname, 'data');
const UPLOAD_DIR = path.join(__dirname, 'uploads');

if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const dbFiles = {
  visitors: path.join(DATA_DIR, 'visitors.json'),
  logins: path.join(DATA_DIR, 'logins.json'),
  messages: path.join(DATA_DIR, 'messages.json'),
};

// 初始化数据文件
Object.values(dbFiles).forEach(f => {
  if (!fs.existsSync(f)) fs.writeFileSync(f, '[]');
});

function readDB(name) {
  try {
    return JSON.parse(fs.readFileSync(dbFiles[name], 'utf8'));
  } catch {
    return [];
  }
}

function writeDB(name, data) {
  fs.writeFileSync(dbFiles[name], JSON.stringify(data, null, 2));
}

// ============ 中间件 ============
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use('/uploads', express.static(UPLOAD_DIR));

// 获取真实IP
function getIP(req) {
  return req.headers['x-forwarded-for']?.split(',')[0]?.trim() ||
         req.headers['x-real-ip'] ||
         req.connection.remoteAddress ||
         req.socket.remoteAddress ||
         '未知';
}

// 访客记录中间件（只记录API请求和页面访问）
const visitPaths = new Set(['/api/messages', '/api/login', '/api/verify']);
app.use((req, res, next) => {
  if (req.path.startsWith('/api/') || req.path.endsWith('.html') || req.path === '/') {
    const ip = getIP(req);
    // 同一IP访问同一页面2分钟内不重复记录，不同页面各自独立
    const now = Date.now();
    const visitors = readDB('visitors');
    const recent = visitors.find(v => v.ip === ip && v.path === req.path && (now - v.ts < 120000));
    if (!recent) {
      visitors.push({
        id: Date.now() + '-' + Math.random().toString(36).slice(2, 8),
        ip, path: req.path,
        ua: req.headers['user-agent'] || '',
        ts: now,
        note: ''
      });
      // 最多保留5000条
      if (visitors.length > 5000) visitors.splice(0, visitors.length - 5000);
      writeDB('visitors', visitors);
    }
  }
  next();
});

// JWT认证中间件
function auth(req, res, next) {
  const token = req.headers.authorization?.replace('Bearer ', '');
  if (!token) return res.status(401).json({ error: '未登录' });
  try {
    req.user = jwt.verify(token, JWT_SECRET);
    next();
  } catch {
    res.status(401).json({ error: 'token无效或已过期' });
  }
}

// ============ 文件上传配置 ============
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, UPLOAD_DIR),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname);
    cb(null, Date.now() + '-' + Math.random().toString(36).slice(2, 8) + ext);
  }
});
const upload = multer({
  storage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB
  fileFilter: (req, file, cb) => {
    const imgTypes = /jpeg|jpg|png|gif|webp|bmp/;
    const audioTypes = /webm|mp3|wav|ogg|m4a|aac/;
    const mime = file.mimetype;
    if (mime.startsWith('image/') && imgTypes.test(path.extname(file.originalname).toLowerCase())) {
      cb(null, true);
    } else if (mime.startsWith('audio/') && audioTypes.test(path.extname(file.originalname).toLowerCase())) {
      cb(null, true);
    } else if (mime.startsWith('image/') || mime.startsWith('audio/')) {
      cb(null, true); // 放宽限制，只要是图片或音频就允许
    } else {
      cb(new Error('只允许上传图片或音频文件'));
    }
  }
});

// ============ 健康检查 ============
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', time: Date.now(), ip: getIP(req) });
});

// ============ 认证接口 ============

// 管理员登录
app.post('/api/login', async (req, res) => {
  const { username, password } = req.body;
  const ip = getIP(req);
  const logins = readDB('logins');

  const record = {
    id: Date.now() + '-' + Math.random().toString(36).slice(2, 8),
    ip, username: username || '',
    ua: req.headers['user-agent'] || '',
    ts: Date.now(),
    status: '失败',
    note: ''
  };

  if (username === ADMIN_USER && password === ADMIN_PASS) {
    const token = jwt.sign({ username }, JWT_SECRET, { expiresIn: '7d' });
    record.status = '成功';
    logins.push(record);
    if (logins.length > 2000) logins.splice(0, logins.length - 2000);
    writeDB('logins', logins);
    res.json({ token, username });
  } else {
    logins.push(record);
    if (logins.length > 2000) logins.splice(0, logins.length - 2000);
    writeDB('logins', logins);
    res.status(401).json({ error: '用户名或密码错误' });
  }
});

// 验证token
app.get('/api/verify', auth, (req, res) => {
  res.json({ valid: true, username: req.user.username });
});

// ============ 访客IP接口（管理员） ============

app.get('/api/visitors', auth, (req, res) => {
  const visitors = readDB('visitors').sort((a, b) => b.ts - a.ts);
  res.json(visitors);
});

app.put('/api/visitors/:id', auth, (req, res) => {
  const { id } = req.params;
  const { note } = req.body;
  const visitors = readDB('visitors');
  const v = visitors.find(x => x.id === id);
  if (!v) return res.status(404).json({ error: '记录不存在' });
  if (note !== undefined) v.note = note;
  writeDB('visitors', visitors);
  res.json(v);
});

app.delete('/api/visitors/:id', auth, (req, res) => {
  const { id } = req.params;
  let visitors = readDB('visitors');
  visitors = visitors.filter(x => x.id !== id);
  writeDB('visitors', visitors);
  res.json({ ok: true });
});

// ============ 登录记录接口（管理员） ============

app.get('/api/logins', auth, (req, res) => {
  const logins = readDB('logins').sort((a, b) => b.ts - a.ts);
  res.json(logins);
});

app.put('/api/logins/:id', auth, (req, res) => {
  const { id } = req.params;
  const { note } = req.body;
  const logins = readDB('logins');
  const l = logins.find(x => x.id === id);
  if (!l) return res.status(404).json({ error: '记录不存在' });
  if (note !== undefined) l.note = note;
  writeDB('logins', logins);
  res.json(l);
});

app.delete('/api/logins/:id', auth, (req, res) => {
  const { id } = req.params;
  let logins = readDB('logins');
  logins = logins.filter(x => x.id !== id);
  writeDB('logins', logins);
  res.json({ ok: true });
});

// ============ 留言接口（公开） ============

app.get('/api/messages', (req, res) => {
  const messages = readDB('messages').sort((a, b) => b.ts - a.ts);
  res.json(messages);
});

app.post('/api/messages', upload.fields([
  { name: 'image', maxCount: 1 },
  { name: 'voice', maxCount: 1 }
]), (req, res) => {
  const { name, content } = req.body;
  if (!content || !content.trim()) {
    return res.status(400).json({ error: '留言内容不能为空' });
  }
  const ip = getIP(req);
  const msg = {
    id: Date.now() + '-' + Math.random().toString(36).slice(2, 8),
    name: (name || '匿名访客').trim().slice(0, 30),
    content: content.trim().slice(0, 1000),
    image: req.files?.image?.[0]?.filename || '',
    voice: req.files?.voice?.[0]?.filename || '',
    ip,
    ts: Date.now()
  };
  const messages = readDB('messages');
  messages.push(msg);
  if (messages.length > 10000) messages.splice(0, messages.length - 10000);
  writeDB('messages', messages);
  res.json(msg);
});

app.delete('/api/messages/:id', auth, (req, res) => {
  const { id } = req.params;
  let messages = readDB('messages');
  const msg = messages.find(m => m.id === id);
  if (msg) {
    // 删除关联文件
    if (msg.image) {
      const p = path.join(UPLOAD_DIR, msg.image);
      if (fs.existsSync(p)) fs.unlinkSync(p);
    }
    if (msg.voice) {
      const p = path.join(UPLOAD_DIR, msg.voice);
      if (fs.existsSync(p)) fs.unlinkSync(p);
    }
  }
  messages = messages.filter(m => m.id !== id);
  writeDB('messages', messages);
  res.json({ ok: true });
});

// ============ 启动服务 ============
app.listen(PORT, () => {
  console.log(`服务已启动: http://localhost:${PORT}`);
  console.log(`管理员账号: ${ADMIN_USER}  密码: ${ADMIN_PASS}`);
  console.log('请及时修改默认密码（通过环境变量 ADMIN_USER / ADMIN_PASS 设置）');
});
