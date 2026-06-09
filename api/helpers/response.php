<?php
// ============================================================
// EGGLAND BD - Response Helper
// ============================================================

class Response {

    public static function success($data = null, string $message = 'Success', int $code = 200): void {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error(string $message = 'Error', int $code = 400, $errors = null): void {
        http_response_code($code);
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): void {
        self::error($message, 404);
    }

    public static function paginated(array $items, int $total, int $page, int $pageSize): void {
        http_response_code(200);
        echo json_encode([
            'success'    => true,
            'data'       => $items,
            'pagination' => [
                'total'      => $total,
                'page'       => $page,
                'page_size'  => $pageSize,
                'total_pages' => ceil($total / $pageSize),
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
