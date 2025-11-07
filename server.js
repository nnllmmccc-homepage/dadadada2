// server.js
import express from "express";
import multer from "multer";
import fs from "fs";
import path from "path";

const app = express();
const __dirname = path.resolve();

// ======================================
// 📁 アップロード設定
// ======================================
const uploadDir = path.join(__dirname, "uploads");
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir);

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, uploadDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname);
    const name = Date.now() + "_" + Math.random().toString(36).substring(2, 8) + ext;
    cb(null, name);
  },
});
const upload = multer({ storage });

// ======================================
// 📄 静的ファイル
// ======================================
app.use(express.static(__dirname));

// ======================================
// 📤 アップロードAPI
// ======================================
app.post("/upload", upload.single("file"), (req, res) => {
  if (!req.file) return res.status(400).json({ error: "ファイルがありません" });

  const filePath = `uploads/${req.file.filename}`;
  const viewUrl = `${req.protocol}://${req.get("host")}/view?img=${encodeURIComponent(filePath)}`;
  res.json({ success: true, url: viewUrl });
});

// ======================================
// 🖼️ Discordプレビュー対応ページ
// ======================================
app.get("/view", (req, res) => {
  const img = req.query.img;
  if (!img) return res.status(400).send("画像が指定されていません。");

  const imageUrl = `${req.protocol}://${req.get("host")}/${img}`;
  const html = `
  <!DOCTYPE html>
  <html lang="ja">
  <head>
    <meta charset="UTF-8">
    <meta property="og:title" content="共有画像">
    <meta property="og:description" content="ImageShareで共有された画像">
    <meta property="og:type" content="article">
    <meta property="og:image" content="${imageUrl}">
    <meta property="og:url" content="${req.protocol}://${req.get("host")}${req.originalUrl}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="${imageUrl}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>共有画像</title>
    <style>
      body {
        background: #f8fafc;
        font-family: 'Inter', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
      }
      img {
        max-width: 90%;
        max-height: 80vh;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      }
    </style>
  </head>
  <body>
    <img src="${imageUrl}" alt="共有画像">
  </body>
  </html>`;
  res.send(html);
});

// ======================================
// 🚀 起動
// ======================================
const PORT = 3000;
app.listen(PORT, () => {
  console.log(`✅ サーバー起動中 → http://localhost:${PORT}`);
});
