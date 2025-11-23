const SMB2 = require('@marsaud/smb2');
const fs = require('fs');
const path = require('path');

class SmbService {
  constructor(config) {
    this.config = config; // { share, domain, username, password, autoCloseTimeout }
  }

  async connect() {
    this.client = new SMB2(this.config);
    // Basic check if connection works (listing root)
    // Note: SMB2 constructor doesn't connect immediately, but methods do.
    // However, we want to verify credentials.
    try {
      await this.client.readdir('');
      return true;
    } catch (error) {
      console.error('SMB Connection failed:', error);
      throw new Error('Failed to connect to SMB share: ' + error.message);
    }
  }

  async list(dirPath) {
    if (!this.client) await this.connect();
    // Ensure path uses backslashes
    const smbPath = dirPath.replace(/\//g, '\\');
    const entries = await this.client.readdir(smbPath, { stats: true });

    // Format entries
    return entries.map(entry => ({
      name: entry.name,
      isDirectory: entry.isDirectory(),
      size: entry.size,
      modified: entry.mtime
    }));
  }

  async readFile(filePath) {
    if (!this.client) await this.connect();
    const smbPath = filePath.replace(/\//g, '\\');
    return await this.client.readFile(smbPath);
  }

  async writeFile(filePath, buffer) {
    if (!this.client) await this.connect();
    const smbPath = filePath.replace(/\//g, '\\');
    return await this.client.writeFile(smbPath, buffer);
  }

  async mkdir(dirPath) {
    if (!this.client) await this.connect();
    const smbPath = dirPath.replace(/\//g, '\\');
    return await this.client.mkdir(smbPath);
  }

  async rename(oldPath, newPath) {
    if (!this.client) await this.connect();
    const smbOldPath = oldPath.replace(/\//g, '\\');
    const smbNewPath = newPath.replace(/\//g, '\\');
    return await this.client.rename(smbOldPath, smbNewPath);
  }

  async delete(pathStr, isDirectory) {
    if (!this.client) await this.connect();
    const smbPath = pathStr.replace(/\//g, '\\');
    if (isDirectory) {
      return await this.client.rmdir(smbPath);
    } else {
      return await this.client.unlink(smbPath);
    }
  }

  async createReadStream(filePath) {
      if (!this.client) await this.connect();
      const smbPath = filePath.replace(/\//g, '\\');
      return await this.client.createReadStream(smbPath);
  }

  async createWriteStream(filePath) {
      if (!this.client) await this.connect();
      const smbPath = filePath.replace(/\//g, '\\');
      return await this.client.createWriteStream(smbPath);
  }

  disconnect() {
    if (this.client) {
      this.client.disconnect();
      this.client = null;
    }
  }
}

module.exports = SmbService;
