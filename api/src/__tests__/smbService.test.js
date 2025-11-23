const SmbService = require('../smbService');
const SMB2 = require('@marsaud/smb2');

jest.mock('@marsaud/smb2');

describe('SmbService', () => {
  let smbService;
  let mockClient;

  beforeEach(() => {
    mockClient = {
      readdir: jest.fn(),
      readFile: jest.fn(),
      writeFile: jest.fn(),
      mkdir: jest.fn(),
      rename: jest.fn(),
      unlink: jest.fn(),
      rmdir: jest.fn(),
      disconnect: jest.fn()
    };
    SMB2.mockImplementation(() => mockClient);

    smbService = new SmbService({
      share: '\\\\server\\share',
      domain: 'DOMAIN',
      username: 'user',
      password: 'password'
    });
  });

  test('connect should initialize client and verify connection', async () => {
    mockClient.readdir.mockResolvedValue([]);
    await smbService.connect();
    expect(SMB2).toHaveBeenCalled();
    expect(mockClient.readdir).toHaveBeenCalledWith('');
  });

  test('list should return formatted entries', async () => {
    mockClient.readdir.mockResolvedValue([
      { name: 'file1.txt', isDirectory: () => false, size: 1024, mtime: new Date() },
      { name: 'folder1', isDirectory: () => true, size: 0, mtime: new Date() }
    ]);

    // Mock connect success
    const connectSpy = jest.spyOn(smbService, 'connect').mockImplementation(async () => {
        smbService.client = mockClient;
    });

    const result = await smbService.list('some/path');

    expect(mockClient.readdir).toHaveBeenCalledWith('some\\path', { stats: true });
    expect(result).toHaveLength(2);
    expect(result[0].name).toBe('file1.txt');
    expect(result[0].isDirectory).toBe(false);
    expect(result[1].name).toBe('folder1');
    expect(result[1].isDirectory).toBe(true);
  });

  test('delete file should call unlink', async () => {
     const connectSpy = jest.spyOn(smbService, 'connect').mockImplementation(async () => {
        smbService.client = mockClient;
    });

    await smbService.delete('file.txt', false);
    expect(mockClient.unlink).toHaveBeenCalledWith('file.txt');
  });

   test('delete folder should call rmdir', async () => {
     const connectSpy = jest.spyOn(smbService, 'connect').mockImplementation(async () => {
        smbService.client = mockClient;
    });

    await smbService.delete('folder', true);
    expect(mockClient.rmdir).toHaveBeenCalledWith('folder');
  });
});
