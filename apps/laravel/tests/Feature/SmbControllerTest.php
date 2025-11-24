<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SmbService;
use Icewind\SMB\IShare;
use Icewind\SMB\IFileInfo;
use Mockery;

class SmbControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_list_files()
    {
        $mockShare = Mockery::mock(IShare::class);
        $mockFile = Mockery::mock(IFileInfo::class);

        $mockFile->shouldReceive('getName')->andReturn('test.txt');
        $mockFile->shouldReceive('getSize')->andReturn(123);
        $mockFile->shouldReceive('isDirectory')->andReturn(false);
        $mockFile->shouldReceive('isHidden')->andReturn(false);

        $mockShare->shouldReceive('dir')->with('/')->andReturn([$mockFile]);

        $mockService = Mockery::mock(SmbService::class);
        $mockService->shouldReceive('getShare')
            ->andReturn($mockShare);

        $this->app->instance(SmbService::class, $mockService);

        $response = $this->get('/api/files?path=/', [
            'X-SMB-Host' => 'localhost',
            'X-SMB-Share' => 'public',
            'X-SMB-User' => 'test',
            'X-SMB-Password' => 'test'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                [
                    'name' => 'test.txt',
                    'size' => 123,
                    'isDirectory' => false,
                    'path' => '/test.txt'
                ]
            ]);
    }

    public function test_upload_file()
    {
        $mockShare = Mockery::mock(IShare::class);
        $mockShare->shouldReceive('put')->once();

        $mockService = Mockery::mock(SmbService::class);
        $mockService->shouldReceive('getShare')->andReturn($mockShare);

        $this->app->instance(SmbService::class, $mockService);

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.txt', 100);

        $response = $this->post('/api/files/upload', [
            'file' => $file,
            'path' => '/uploads'
        ], [
            'X-SMB-Host' => 'localhost',
            'X-SMB-Share' => 'public',
            'X-SMB-User' => 'test',
            'X-SMB-Password' => 'test'
        ]);

        $response->assertStatus(200);
    }
}
