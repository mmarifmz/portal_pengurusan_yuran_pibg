<x-layouts::app :title="__('Backup DB')">
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">System Admin</p>
                <h1 class="text-2xl font-bold text-zinc-900">Backup DB</h1>
                <p class="text-sm text-zinc-500">Create and manage downloadable SQL backups.</p>
            </div>
            <a href="{{ route('teacher.reconcile.index') }}" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                Back to Year Reconcile
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900">Teacher Backup</h2>
                    <p class="text-xs text-zinc-500">Create a downloadable SQL backup before/after reconcile.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('system.backups.create') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">
                            Create backup now
                        </button>
                    </form>
                    <form method="POST" action="{{ route('system.backups.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="backup_file" accept=".sql,.gz,.sql.gz" required class="block rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-700" />
                        <button type="submit" class="inline-flex items-center rounded-xl border border-sky-300 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-100">
                            Upload backup
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @forelse ($backupFiles as $file)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700">{{ $file['name'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ number_format(((int) $file['size']) / 1024, 1) }} KB</td>
                                <td class="px-4 py-3 text-zinc-700">{{ \Illuminate\Support\Carbon::createFromTimestamp((int) $file['last_modified'])->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('system.backups.download', ['fileName' => $file['name'], 'format' => 'sql']) }}" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                            Download SQL
                                        </a>
                                        <a href="{{ route('system.backups.download', ['fileName' => $file['name']]) }}" class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50">
                                            Download GZ
                                        </a>
                                        <form method="POST" action="{{ route('system.backups.restore', ['fileName' => $file['name']]) }}" onsubmit="return confirm('Rollback database using this backup? Preflight safety checks will run (environment, SQL validation, schema sanity), then a safety backup is created before restore. Continue?')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100">
                                                Rollback
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('system.backups.delete', ['fileName' => $file['name']]) }}" onsubmit="return confirm('Delete this backup permanently?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-zinc-500">No backup file yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
