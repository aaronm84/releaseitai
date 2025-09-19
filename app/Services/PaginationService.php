<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class PaginationService
{
    /**
     * Transform paginated data into a standardized API response format.
     */
    public function formatPaginatedResponse(LengthAwarePaginator $paginator): array
    {
        $paginatedArray = $paginator->toArray();

        return [
            'data' => $paginatedArray['data'],
            'links' => [
                'first' => $paginatedArray['first_page_url'],
                'last' => $paginatedArray['last_page_url'],
                'prev' => $paginatedArray['prev_page_url'],
                'next' => $paginatedArray['next_page_url'],
            ],
            'meta' => [
                'current_page' => $paginatedArray['current_page'],
                'from' => $paginatedArray['from'],
                'last_page' => $paginatedArray['last_page'],
                'per_page' => $paginatedArray['per_page'],
                'to' => $paginatedArray['to'],
                'total' => $paginatedArray['total'],
                'path' => $paginatedArray['path']
            ]
        ];
    }

    /**
     * Create a JSON response with standardized pagination format.
     */
    public function jsonResponse(LengthAwarePaginator $paginator, int $status = 200): JsonResponse
    {
        return response()->json($this->formatPaginatedResponse($paginator), $status);
    }

    /**
     * Transform paginated data with custom data formatting.
     */
    public function formatPaginatedResponseWithTransformer(LengthAwarePaginator $paginator, callable $transformer): array
    {
        $formattedData = $paginator->getCollection()->map($transformer)->all();

        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = $formattedData;

        return [
            'data' => $paginatedArray['data'],
            'links' => [
                'first' => $paginatedArray['first_page_url'],
                'last' => $paginatedArray['last_page_url'],
                'prev' => $paginatedArray['prev_page_url'],
                'next' => $paginatedArray['next_page_url'],
            ],
            'meta' => [
                'current_page' => $paginatedArray['current_page'],
                'from' => $paginatedArray['from'],
                'last_page' => $paginatedArray['last_page'],
                'per_page' => $paginatedArray['per_page'],
                'to' => $paginatedArray['to'],
                'total' => $paginatedArray['total'],
                'path' => $paginatedArray['path']
            ]
        ];
    }
}