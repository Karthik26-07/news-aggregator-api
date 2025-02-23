<?php

namespace App\Traits;

trait ApiResponse
{
    public function apiResponse($data = null, string $message = 'Success', int $status = 200, array $headers = [])
    {
        $response = [
            'success' => $status >= 200 && $status < 300, // True for 2xx status codes
            'message' => $message,
            'status' => $status,
        ];

        if ($data != null) {
            $response['data'] = $data;
        }
        return response()->json($response, $status, $headers);
    }

    /**
     * Success response shorthand.
     */
    public function successResponse($data = null, string $message = 'Success', int $status = 200)
    {
        return $this->apiResponse($data, $message, $status);
    }

    /**
     * Error response shorthand.
     */
    public function errorResponse(string $message = 'Error', int $status = 400, $data = null)
    {
        return $this->apiResponse($data, $message, $status);
    }
}
