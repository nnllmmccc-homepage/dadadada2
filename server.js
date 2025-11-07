import express from "express";
import multer from "multer";
import path from "path";
import fs from "fs";

const app = express();
const PORT = 3000;

// uploads フォルダ作成
const uploadDir = path.resolve("uploads");
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir);

// 画像保存設定
const storage = multer.diskStorage({
  destination: (_, __, cb) => cb(null, "uploads/"),
  filename: (_, file, cb) => {
    const ext = path.extname(file.originalname);
    const name = Date.now() + "_" + Math.random().toString(36).substring(2, 8) + ext;
    cb(null, name);
  }
});
const upload = multer({ storage });

// 静的ファイル
app.use(express.static("."));
app.use("/uploads", express.static("uploads"));

// 画像アップロードAPI
app.post("/upload", upload.single("file"), (req, res) => {
  const imageUrl = `${req.protocol}://${req.get("host")}/view.html?url=${encodeURIComponent("/uploads/" + req.file.filename)}`;
  res.json({ success: true, url: imageUrl });
});

app.listen(PORT, () => console.log(`✅ サーバー起動: http://localhost:${PORT}`));
