@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
<div class="head"><h1>Backups & Recovery</h1></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!empty($status['last_error']))<div class="alert alert-danger" role="alert">Backup needs attention: {{ $status['last_error'] }}</div>@endif
@if(!empty($status['verification_error']))<div class="alert alert-danger" role="alert">Restore test failed: {{ $status['verification_error'] }}</div>@endif
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Daily protection</h2>
<p>Automatic backup at <strong>{{ config('backup.time') }} Nairobi time</strong>, @if(config('backup.hosted'))automatically on the hosting server. Your computer can be switched off.@else when this computer is running and your Linux session is active. Missed runs resume when you sign in again.@endif Includes the full database and uploaded files, encrypted before saving.</p>
<dl><dt>{{ config('backup.hosted') ? 'Last server backup' : 'Last local backup' }}</dt><dd>{{ !empty($status['last_success']) ? \Carbon\Carbon::parse($status['last_success'])->timezone('Africa/Nairobi')->format('d M Y, g:i a') : 'No successful backup yet' }}</dd>
<dt>{{ config('backup.hosted') ? 'Second backup copy' : 'Off-computer copy' }}</dt><dd>{{ config('backup.hosted') ? str_replace('local copy', 'server copy', $status['external_status'] ?? 'Not configured — only a local copy exists.') : ($status['external_status'] ?? 'Not configured — only a local copy exists.') }}</dd>
<dt>Last successful restore test</dt><dd>{{ !empty($status['last_verified']) ? \Carbon\Carbon::parse($status['last_verified'])->timezone('Africa/Nairobi')->format('d M Y, g:i a') : 'Not tested yet' }}</dd></dl>
<p class="text-muted">Keeps the newest backup from 7 days, 4 weeks and 12 months. Safety backups made before imports, updates and restores are kept separately. A restore test runs every Sunday at 22:30 using a separate database.</p>
<form method="POST" action="{{ route('backup_create') }}">@csrf<button class="btn btn-primary">Back up now</button></form>
</div></div>
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Google Drive backups</h2>
<p>Connect your Google account once. We’ll create a private <strong>DEMO RETAIL Backups</strong> folder automatically and save your encrypted backups there.</p>
<p class="text-muted">Your recovery key stays separate and is never uploaded.</p>
@if($google['connected'])
<p>Google Drive is connected. Uploads run with your daily backups.</p>
@if($google['folder_link'])
<p><a href="{{ $google['folder_link'] }}" target="_blank" rel="noopener noreferrer">Open DEMO RETAIL Backups</a></p>
@else
<p class="text-muted">Folder setup is pending. Reconnect to finish it.</p>
@endif
<form method="POST" action="{{ route('backup_google_connect') }}">@csrf<button class="btn btn-outline-primary mb-2">Reconnect Google Drive</button></form>
<form method="POST" action="{{ route('backup_google_disconnect') }}">@csrf<button class="btn btn-outline-secondary">Disconnect Google Drive</button></form>
@else
@if($google['ready'])
<form method="POST" action="{{ route('backup_google_connect') }}">@csrf<button class="btn btn-primary">Connect Google Drive</button></form>
@else
<button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#googleSetup" aria-expanded="false" aria-controls="googleSetup">Connect Google Drive</button>
<div class="collapse mt-3" id="googleSetup">
<h3 class="h6">One-time Google setup</h3>
<p>Create a project in <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>, enable the Google Drive API, and create an OAuth client of type <strong>Web application</strong>.</p>
<label class="form-label" for="googleCallbackUrl">Authorized redirect URI — copy this into Google Cloud</label>
<input id="googleCallbackUrl" class="form-control mb-3" readonly value="{{ $googleCallbackUrl }}">
<form method="POST" action="{{ route('backup_google_setup') }}">@csrf
<label for="googleClientId" class="form-label">Google Client ID</label>
<input id="googleClientId" name="google_client_id" class="form-control mb-3" required maxlength="300" placeholder="…apps.googleusercontent.com" autocomplete="off">
<label for="googleClientSecret" class="form-label">Google Client Secret</label>
<input type="password" id="googleClientSecret" name="google_client_secret" class="form-control mb-3" required minlength="8" maxlength="4096" autocomplete="new-password">
<p class="text-muted">Your secret is stored encrypted. After saving, click Connect Google Drive again to sign in.</p>
<button class="btn btn-primary">Save Google setup</button>
</form>
</div>
@endif
@endif
</div></div>
<div class="card mb-3"><div class="card-body"><h2 class="h5">Keep your recovery key safe</h2>
<p>Save the recovery key on a separate secure drive or password manager. You need it to recover these backups if {{ config('backup.hosted') ? 'the hosting server' : 'this computer' }} fails. Keep it separate from the backup files.</p>
<a class="btn btn-outline-secondary" href="{{ route('backup_recovery_key') }}">Save recovery key</a>
</div></div>
<div class="card"><div class="card-body">
<table class="table"><thead><tr><th>Backup</th><th>Size (KB)</th><th>Actions</th></tr></thead><tbody>
@forelse($files as $file)<tr><td>{{ $file->created }}<br><small>{{ $file->name }}</small></td><td>{{ $file->size }}</td><td>
<a class="btn btn-sm btn-outline-primary mb-2" href="{{ route('backup_download', $file->name) }}">Download</a>
<form method="POST" action="{{ route('backup_verify') }}" class="mb-2">@csrf<input type="hidden" name="file" value="{{ $file->name }}"><button class="btn btn-sm btn-outline-secondary">Test recovery</button></form>
<details class="mb-2"><summary>Restore this backup</summary><p class="text-danger">Replaces current sales, stock, users and uploaded files. A safety backup is saved first. Type RESTORE to confirm.</p><form method="POST" action="{{ route('backup_restore') }}">@csrf<input type="hidden" name="file" value="{{ $file->name }}"><input aria-label="Restore confirmation" class="form-control mb-2" name="confirmation" required autocomplete="off" placeholder="RESTORE"><button class="btn btn-danger">Confirm restore</button></form></details>
<details><summary>Delete local copy</summary><form method="POST" action="{{ route('backup_delete') }}">@csrf @method('DELETE')<input type="hidden" name="file" value="{{ $file->name }}"><label>Type DELETE to confirm<input class="form-control mb-2" name="confirmation" required autocomplete="off"></label><button class="btn btn-outline-danger">Delete</button></form></details>
</td></tr>@empty<tr><td colspan="3">No encrypted backups yet.</td></tr>@endforelse
</tbody></table></div></div>
</section>
@endsection
