<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrainDumpProcessor;
use App\Exceptions\BrainDumpProcessingException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BrainDumpController extends Controller
{
    public function __construct(
        private BrainDumpProcessor $brainDumpProcessor
    ) {}

    public function process(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|min:10|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Process the content
            $result = $this->brainDumpProcessor->process(
                $request->content,
                $request->user()
            );

            $processingTime = round((microtime(true) - $startTime) * 1000);

            Log::info('Brain dump processed successfully', [
                'user_id' => $request->user()->id,
                'content_length' => strlen($request->content),
                'processing_time_ms' => $processingTime,
                'tasks_found' => count($result['tasks'] ?? []),
                'meetings_found' => count($result['meetings'] ?? []),
                'decisions_found' => count($result['decisions'] ?? [])
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'processing_time' => $processingTime,
                'timestamp' => now()->toISOString(),
                'content_id' => $result['content_id'] ?? null
            ]);

        } catch (BrainDumpProcessingException $e) {
            Log::error('Brain dump processing failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => $e->getErrorType()
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected brain dump processing error', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing your content',
                'error_type' => 'processing_error'
            ], 500);
        }
    }
}