const express = require('express');
const router = express.Router();
const { authenticateToken, generateToken } = require('./auth');
const SmbService = require('./smbService');
const multer = require('multer');
const upload = multer({ dest: 'uploads/' }); // Temporary storage
const fs = require('fs');

// Helper to get SmbService from request user (credentials)
function getSmbService(user) {
  // In a real app, we should encrypt/decrypt the password in the token
  // Or store the credentials in a server-side session/cache
  // For this migration, we will use the credentials from the JWT payload (careful with security!)
  // The prompt asks for "some method of authentication".
  return new SmbService({
    share: user.share,
    domain: user.domain,
    username: user.username,
    password: user.password,
    autoCloseTimeout: 0
  });
}

// Login
router.post('/login', async (req, res) => {
  const { share, domain, username, password } = req.body;

  if (!share || !username || !password) {
    return res.status(400).json({ error: 'Missing credentials' });
  }

  const smb = new SmbService({ share, domain, username, password });
  try {
    await smb.connect();
    smb.disconnect();

    // Successful connection, generate token
    const token = generateToken({ share, domain, username, password });
    res.json({ token });
  } catch (error) {
    res.status(401).json({ error: 'Authentication failed: ' + error.message });
  }
});

// List files
router.get('/files', authenticateToken, async (req, res) => {
  const smb = getSmbService(req.user);
  const dirPath = req.query.path || '';

  try {
    const files = await smb.list(dirPath);
    res.json(files);
  } catch (error) {
    res.status(500).json({ error: error.message });
  } finally {
    smb.disconnect();
  }
});

// Download file
router.get('/download', authenticateToken, async (req, res) => {
  const smb = getSmbService(req.user);
  const filePath = req.query.path;

  if (!filePath) return res.status(400).json({ error: 'Missing path' });

  try {
    // Stream the file
    const readStream = await smb.createReadStream(filePath);
    res.setHeader('Content-Disposition', `attachment; filename="${filePath.split('/').pop()}"`);
    readStream.pipe(res);
    readStream.on('end', () => smb.disconnect());
    readStream.on('error', (err) => {
        console.error(err);
        res.status(500).end();
        smb.disconnect();
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
    smb.disconnect();
  }
});

// Upload file
router.post('/upload', authenticateToken, upload.single('file'), async (req, res) => {
  const smb = getSmbService(req.user);
  const dirPath = req.body.path || '';
  const file = req.file;

  if (!file) return res.status(400).json({ error: 'No file uploaded' });

  try {
    const filePath = (dirPath ? dirPath + '\\' : '') + file.originalname;
    const fileBuffer = fs.readFileSync(file.path);
    await smb.writeFile(filePath, fileBuffer);

    // Cleanup temp file
    fs.unlinkSync(file.path);

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ error: error.message });
  } finally {
    smb.disconnect();
  }
});

// New Folder
router.post('/mkdir', authenticateToken, async (req, res) => {
    const smb = getSmbService(req.user);
    const dirPath = req.body.path;

    if (!dirPath) return res.status(400).json({ error: 'Missing path' });

    try {
        await smb.mkdir(dirPath);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    } finally {
        smb.disconnect();
    }
});

// Rename
router.post('/rename', authenticateToken, async (req, res) => {
    const smb = getSmbService(req.user);
    const { oldPath, newPath } = req.body;

    if (!oldPath || !newPath) return res.status(400).json({ error: 'Missing paths' });

    try {
        await smb.rename(oldPath, newPath);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    } finally {
        smb.disconnect();
    }
});

// Delete
router.post('/delete', authenticateToken, async (req, res) => {
    const smb = getSmbService(req.user);
    const { path, isDirectory } = req.body;

    if (!path) return res.status(400).json({ error: 'Missing path' });

    try {
        await smb.delete(path, isDirectory);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    } finally {
        smb.disconnect();
    }
});

module.exports = router;
