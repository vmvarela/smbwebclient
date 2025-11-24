<?php

namespace App\Http\Controllers;

use App\Services\SmbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmbController extends Controller
{
    protected $smbService;

    public function __construct(SmbService $smbService)
    {
        $this->smbService = $smbService;
    }

    private function getShare(Request $request)
    {
        // Headers or Query params for auth?
        // For simplicity, we'll accept them in headers or body,
        // but typically this should be in a session or JWT.
        // Assuming headers for now: X-SMB-Host, X-SMB-Share, X-SMB-User, X-SMB-Password

        $host = $request->header('X-SMB-Host') ?? 'localhost';
        $shareName = $request->header('X-SMB-Share') ?? 'public';
        $user = $request->header('X-SMB-User') ?? 'test';
        $password = $request->header('X-SMB-Password') ?? 'test';

        // Override for local docker testing if not provided
        if ($host === 'localhost' && $request->has('use_docker_samba')) {
             $host = 'samba';
        }

        return $this->smbService->getShare($host, $shareName, $user, $password);
    }

    public function list(Request $request)
    {
        $path = $request->query('path', '/');
        try {
            $share = $this->getShare($request);
            $files = $share->dir($path);

            // Format for frontend
            $formatted = [];
            foreach ($files as $file) {
                $formatted[] = [
                    'name' => $file->getName(),
                    'size' => $file->getSize(),
                    'isDirectory' => $file->isDirectory(),
                    'isHidden' => $file->isHidden(),
                    'path' => $path . ($path === '/' ? '' : '/') . $file->getName()
                ];
            }
            return response()->json($formatted);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'path' => 'required|string'
        ]);

        try {
            $share = $this->getShare($request);
            $uploadedFile = $request->file('file');
            $targetPath = $request->input('path') . '/' . $uploadedFile->getClientOriginalName();

            // Fix double slashes
            $targetPath = str_replace('//', '/', $targetPath);

            $share->put($uploadedFile->getRealPath(), $targetPath);

            return response()->json(['message' => 'Uploaded successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function download(Request $request)
    {
        $path = $request->query('path');
        if (!$path) {
            return response()->json(['error' => 'Path required'], 400);
        }

        try {
            $share = $this->getShare($request);

            // Create a temp file to stream to
            $tempFile = tempnam(sys_get_temp_dir(), 'smb_down');
            $share->get($path, $tempFile);

            return response()->download($tempFile, basename($path))->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createFolder(Request $request)
    {
        $path = $request->input('path');
        try {
            $share = $this->getShare($request);
            $share->mkdir($path);
            return response()->json(['message' => 'Folder created']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $path = $request->query('path');
        try {
            $share = $this->getShare($request);
            // Check if folder or file? The library has `del` for files and `rmdir` for folders usually,
            // but icewind/smb typically handles it or we need to check stats first.
            // Let's assume file for now or try both.
            // The library exposes `stat`.

            $info = $share->stat($path);
            if ($info->isDirectory()) {
                $share->rmdir($path);
            } else {
                $share->del($path);
            }

            return response()->json(['message' => 'Deleted successfully']);
        } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function rename(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');

        try {
            $share = $this->getShare($request);
            $share->rename($from, $to);
            return response()->json(['message' => 'Renamed successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
