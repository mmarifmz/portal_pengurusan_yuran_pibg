<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Shetabit\Visitor\Models\Visit;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $visits = $this->filteredQuery($filters)
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('system.visitor-logs', [
            'visits' => $visits,
            'keyword' => $filters['keyword'],
            'method' => $filters['method'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $filename = 'visitor-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'id',
                'visited_at',
                'ip',
                'method',
                'url',
                'referer',
                'browser',
                'platform',
                'device',
                'visitor_type',
                'visitor_id',
            ]);

            $this->filteredQuery($filters)
                ->latest('id')
                ->chunkById(500, function (Collection $rows) use ($output): void {
                    foreach ($rows as $visit) {
                        fputcsv($output, [
                            $visit->id,
                            optional($visit->created_at)->format('Y-m-d H:i:s'),
                            $visit->ip,
                            $visit->method,
                            $visit->url,
                            $visit->referer,
                            $visit->browser,
                            $visit->platform,
                            $visit->device,
                            $visit->visitor_type,
                            $visit->visitor_id,
                        ]);
                    }
                });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{keyword:string,method:string,date_from:?string,date_to:?string}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', 'string', 'max:10'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        return [
            'keyword' => trim((string) ($filters['q'] ?? '')),
            'method' => strtoupper(trim((string) ($filters['method'] ?? ''))),
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
    }

    /**
     * @param array{keyword:string,method:string,date_from:?string,date_to:?string} $filters
     */
    private function filteredQuery(array $filters)
    {
        $dateFrom = $filters['date_from']
            ? Carbon::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay()
            : null;
        $dateTo = $filters['date_to']
            ? Carbon::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay()
            : null;

        return Visit::query()
            ->when($filters['keyword'] !== '', function ($query) use ($filters): void {
                $query->where(function ($inner) use ($filters): void {
                    $like = '%'.$filters['keyword'].'%';

                    $inner
                        ->where('ip', 'like', $like)
                        ->orWhere('url', 'like', $like)
                        ->orWhere('referer', 'like', $like)
                        ->orWhere('browser', 'like', $like)
                        ->orWhere('platform', 'like', $like)
                        ->orWhere('device', 'like', $like)
                        ->orWhere('useragent', 'like', $like);
                });
            })
            ->when($filters['method'] !== '', fn ($query) => $query->where('method', $filters['method']))
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo));
    }
}
