<?php
namespace App\Http\Controllers;
use App\Services\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct() { $this->middleware('auth'); }
    public function index(BackupService $backups)
    {
        $files = collect($backups->files())->map(fn ($path) => (object) ['name' => basename($path), 'size' => round(filesize($path) / 1024, 1), 'created' => \Carbon\Carbon::createFromTimestamp(filemtime($path))->timezone('Africa/Nairobi')->format('d/m/Y H:i')]);
        $googleService = app(\App\Services\GoogleDriveBackup::class);
        $google = $googleService->settings();
        $googleCallbackUrl = $googleService->callbackUrl();
        $status = $backups->status();
        if (!$google['connected'] && !$google['last_upload']) $status['external_status'] = config('backup.external_path') ? ($status['external_status'] ?? 'External drive configured.') : 'Google Drive is not connected — only a local copy exists.';
        return view('backup.backup_index', compact('files', 'status', 'google', 'googleCallbackUrl'));
    }
    private function execute(callable $action, string $success)
    {
        set_time_limit(1200);
        try { $action(); return redirect()->route('backup')->with('success', $success); }
        catch (\Throwable $e) { report($e); return redirect()->route('backup')->with('error', $e->getMessage()); }
    }
    public function create(BackupService $backups) { return $this->execute(fn () => $backups->create(), 'Encrypted backup saved.'); }
    public function download(string $file, BackupService $backups) { return response()->download($backups->path($file), null, ['Cache-Control' => 'private, no-store'])->setPrivate(); }
    public function verify(Request $request, BackupService $backups)
    {
        $request->validate(['file' => 'required|string']);
        return $this->execute(fn () => $backups->verify($request->file), 'Restore test passed in a separate temporary database. Live data was not changed.');
    }
    public function restore(Request $request, BackupService $backups)
    {
        $request->validate(['file' => 'required|string', 'confirmation' => 'required|in:RESTORE']);
        return $this->execute(function () use ($backups, $request) {
            $backups->restore($request->file);
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }, 'Backup restored. A pre-restore safety backup was saved. Please sign in again.');
    }
    public function destroy(Request $request, BackupService $backups)
    {
        $request->validate(['file' => 'required|string', 'confirmation' => 'required|in:DELETE']);
        unlink($backups->path($request->file));
        return redirect()->route('backup')->with('success', 'Local backup deleted.');
    }
    public function setupGoogle(Request $request, \App\Services\GoogleDriveBackup $google)
    {
        $values = $request->validate([
            'google_client_id' => ['required', 'string', 'max:300', 'regex:/^[A-Za-z0-9_-]+\.apps\.googleusercontent\.com$/D'],
            'google_client_secret' => 'required|string|min:8|max:4096',
        ]);
        $google->saveSetup($values['google_client_id'], $values['google_client_secret']);
        return redirect()->route('backup')->with('success', 'Google setup saved securely. Click Connect Google Drive to sign in.');
    }
    public function connectGoogle(Request $request, \App\Services\GoogleDriveBackup $google)
    {
        $state = bin2hex(random_bytes(32)); $request->session()->put('backup_google_state', $state);
        try { return redirect()->away($google->authorizationUrl($state)); }
        catch (\Throwable $e) { return redirect()->route('backup')->with('error', $e->getMessage()); }
    }
    public function googleCallback(Request $request, \App\Services\GoogleDriveBackup $google)
    {
        $expected = $request->session()->pull('backup_google_state');
        abort_unless(is_string($expected) && is_string($request->query('state')) && hash_equals($expected, $request->query('state')), 403);
        if ($request->query('error') || !$request->query('code')) return redirect()->route('backup')->with('error', 'Google Drive connection was cancelled.');
        return $this->execute(fn () => $google->connect($request->query('code')), 'Google Drive connected. Encrypted backups will be saved automatically in DEMO RETAIL Backups.');
    }
    public function disconnectGoogle(\App\Services\GoogleDriveBackup $google)
    {
        $google->disconnect(); return redirect()->route('backup')->with('success', 'Automatic Google Drive uploads disconnected.');
    }
    public function recoveryKey()
    {
        abort_unless(is_file(config('backup.key_path')), 404);
        return response('RETAILDEMO_KEY_V1:'.base64_encode(file_get_contents(config('backup.key_path')))."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="retaildemo-backup-recovery.key"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
