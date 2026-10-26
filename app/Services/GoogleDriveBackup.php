<?php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveBackup
{
    private function path(): string { return config('backup.google.settings_path', storage_path('app/google-drive-backup.json'));  }
    private function read(): array { return is_file($this->path()) ? json_decode(file_get_contents($this->path()), true) ?? [] : []; }
    private function write(array $settings): void
    {
        $temp = $this->path().'.'.bin2hex(random_bytes(5)).'.tmp';
        file_put_contents($temp, json_encode($settings), LOCK_EX); chmod($temp, 0600);
        if (!rename($temp, $this->path())) throw new RuntimeException('Could not save Google Drive settings.');
    }
    private function credentials(): array
    {
        $settings = $this->read();
        if (!empty($settings['client_id']) && !empty($settings['client_secret'])) {
            try { return [$settings['client_id'], Crypt::decryptString($settings['client_secret'])]; }
            catch (\Throwable $e) { return [null, null]; }
        }
        return [config('backup.google.client_id'), config('backup.google.client_secret')];
    }
    public function configured(): bool { [$id, $secret] = $this->credentials(); return (bool) ($id && $secret); }
    public function saveSetup(string $id, string $secret): void
    {
        $settings = $this->read();
        $settings['client_id'] = trim($id); $settings['client_secret'] = Crypt::encryptString(trim($secret));
        unset($settings['refresh_token'], $settings['folder_id'], $settings['folder_link'], $settings['folder_managed'], $settings['last_upload']);
        $this->write($settings);
    }
    public function callbackUrl(): string { return $this->redirectUri(); }
    public function settings(): array
    {
        $settings = $this->read();
        return ['folder_link' => $settings['folder_link'] ?? '', 'folder_id' => $settings['folder_id'] ?? null,
            'connected' => !empty($settings['refresh_token']), 'ready' => $this->configured(),
            'last_upload' => $settings['last_upload'] ?? null];
    }
    public function authorizationUrl(string $state): string
    {
        if (!$this->configured()) throw new RuntimeException('Google Drive connection needs its one-time server setup before you can sign in.');
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->credentials()[0], 'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state,
        ]);
    }
    private function redirectUri(): string { return config('backup.google.redirect_uri') ?: route('backup_google_callback'); }
    public function connect(string $code): void
    {
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->credentials()[0], 'client_secret' => $this->credentials()[1],
            'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code', 'code' => $code,
        ]);
        if (!$response->successful() || !$response->json('refresh_token')) throw new RuntimeException('Google Drive authorization failed. Please reconnect and allow access.');
        $settings = $this->read(); $settings['refresh_token'] = Crypt::encryptString($response->json('refresh_token'));
        unset($settings['folder_id'], $settings['folder_link'], $settings['last_upload']);
        $this->write($settings);
        $this->ensureFolder($response->json('access_token') ?: $this->accessToken());
    }
    public function disconnect(): void { $settings = $this->read(); unset($settings['refresh_token']); $this->write($settings); }
    private function accessToken(): string
    {
        $settings = $this->read();
        if (!$this->configured() || empty($settings['refresh_token'])) throw new RuntimeException('Local backup saved. Connect Google Drive to enable its off-computer copy.');
        try { $token = Crypt::decryptString($settings['refresh_token']); }
        catch (\Throwable $e) { throw new RuntimeException('Reconnect Google Drive: its saved authorization cannot be read.'); }
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->credentials()[0], 'client_secret' => $this->credentials()[1],
            'grant_type' => 'refresh_token', 'refresh_token' => $token,
        ]);
        if (!$response->successful() || !$response->json('access_token')) throw new RuntimeException('Local backup saved. Google Drive authorization expired or failed; reconnect it.');
        return $response->json('access_token');
    }
    private function ensureFolder(string $token): string
    {
        $settings = $this->read();
        if (!empty($settings['folder_managed']) && !empty($settings['folder_id'])) return $settings['folder_id'];
        $folders = Http::withToken($token)->timeout(30)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "trashed = false and mimeType = 'application/vnd.google-apps.folder' and 'root' in parents and appProperties has { key='retaildemoBackupFolder' and value='1' }",
            'fields' => 'files(id)', 'pageSize' => 1, 'spaces' => 'drive',
        ]);
        if (!$folders->successful()) throw new RuntimeException('Google Drive connected, but the backup folder could not be checked. Try reconnecting.');
        $id = $folders->json('files.0.id');
        if (!$id) {
            $folder = Http::withToken($token)->timeout(30)->post('https://www.googleapis.com/drive/v3/files?fields=id', [
                'name' => 'DEMO RETAIL Backups', 'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => ['root'], 'appProperties' => ['retaildemoBackupFolder' => '1'],
            ]);
            if (!$folder->successful()) throw new RuntimeException('Google Drive connected, but its backup folder could not be created. Try reconnecting.');
            $id = $folder->json('id');
        }
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $id)) throw new RuntimeException('Google Drive returned an invalid backup folder.');
        $settings['folder_id'] = $id; $settings['folder_managed'] = true;
        $settings['folder_link'] = 'https://drive.google.com/drive/folders/'.$id;
        $this->write($settings);
        return $id;
    }
    public function upload(string $path): void
    {
        $token = $this->accessToken();
        $folderId = $this->ensureFolder($token);
        $settings = $this->read();
        $folder = Http::withToken($token)->timeout(30)->get('https://www.googleapis.com/drive/v3/files/'.$folderId, [
            'fields' => 'mimeType,trashed,capabilities(canAddChildren)', 'supportsAllDrives' => 'true',
        ]);
        if (!$folder->successful() || $folder->json('mimeType') !== 'application/vnd.google-apps.folder'
            || $folder->json('trashed') || !$folder->json('capabilities.canAddChildren')) throw new RuntimeException('Local backup saved. Google Drive folder is missing or you do not have permission to upload there.');
        $start = Http::withToken($token)->timeout(30)->withHeaders([
            'X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) filesize($path),
        ])->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,size,md5Checksum', [
            'name' => basename($path), 'parents' => [$folderId], 'appProperties' => ['retaildemoBackup' => '1'],
        ]);
        $location = $start->header('Location'); $parsed = $location ? parse_url($location) : [];
        if (!$start->successful() || ($parsed['scheme'] ?? '') !== 'https' || ($parsed['host'] ?? '') !== 'www.googleapis.com') throw new RuntimeException('Could not start Google Drive backup upload.');
        $stream = fopen($path, 'rb');
        try {
            $response = Http::withToken($token)->timeout(600)->withOptions(['allow_redirects' => false])->withHeaders(['Content-Length' => (string) filesize($path)])
                ->withBody($stream, 'application/octet-stream')->put($location);
        } finally { fclose($stream); }
        if (!$response->successful() || !$response->json('id') || (int) $response->json('size') !== filesize($path)
            || !hash_equals(hash_file('md5', $path), (string) $response->json('md5Checksum'))) throw new RuntimeException('Google Drive upload did not pass size and checksum verification. Local backup is safe.');
        // Never upload the recovery key. Cloud copies are kept until the owner removes them.
        $settings['last_upload'] = now()->toIso8601String(); $this->write($settings);
    }
}
