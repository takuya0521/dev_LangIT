<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreUserRequest;
use App\Http\Requests\Api\Admin\UpdateUserRequest;
use App\Http\Requests\Api\Admin\ResetUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * F01_02 アカウント初期発行（API02）
     *
     * POST /api/admin/users
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        // メールアドレスを小文字・トリムに正規化
        $email = strtolower(trim($data['email']));

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $email,
            'role'     => $data['role'],
            'password' => $data['password'],
            'status'   => 'active', // 初期状態は active
        ]);

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role,
            'status' => $user->status,
        ], Response::HTTP_CREATED); // 201
    }

    /**
     * F01_03 ユーザー一覧取得（管理者用）
     *
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // ---------------------------
        // 既存フィルタ（role / status）
        // ---------------------------

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // ---------------------------
        // 追加フィルタ（学籍・属性系）
        // ---------------------------

        if ($studentNumber = $request->query('student_number')) {
            $query->where('student_number', $studentNumber);
        }

        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        if ($className = $request->query('class_name')) {
            $query->where('class_name', $className);
        }

        if ($course = $request->query('course')) {
            $query->where('course', $course);
        }

        // ---------------------------
        // 入塾日（enrolled_at）の範囲
        // ---------------------------

        if ($enrolledFrom = $request->query('enrolled_from')) {
            $query->whereDate('enrolled_at', '>=', $enrolledFrom);
        }

        if ($enrolledTo = $request->query('enrolled_to')) {
            $query->whereDate('enrolled_at', '<=', $enrolledTo);
        }

        // ---------------------------
        // 最終ログイン日時での絞り込み
        // ---------------------------

        if ($lastLoginBefore = $request->query('last_login_before')) {
            $query->where(function ($q) use ($lastLoginBefore) {
                $q->whereNull('last_login_at')
                  ->orWhere('last_login_at', '<', $lastLoginBefore);
            });
        }

        if ($lastLoginFrom = $request->query('last_login_from')) {
            $query->whereNotNull('last_login_at')
                  ->where('last_login_at', '>=', $lastLoginFrom);
        }

        $users = $query
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'email',
                'role',
                'status',
                'student_number',
                'grade',
                'class_name',
                'course',
                'enrolled_at',
                'left_at',
                'last_login_at',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'data' => $users,
        ], Response::HTTP_OK);
    }

    /**
     * ユーザーCSVエクスポート
     *
     * GET /api/admin/users/export
     */
    public function export(Request $request)
    {
        $query = User::query();

        // index() と同じフィルタを適用
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($studentNumber = $request->query('student_number')) {
            $query->where('student_number', $studentNumber);
        }

        if ($grade = $request->query('grade')) {
            $query->where('grade', $grade);
        }

        if ($className = $request->query('class_name')) {
            $query->where('class_name', $className);
        }

        if ($course = $request->query('course')) {
            $query->where('course', $course);
        }

        if ($enrolledFrom = $request->query('enrolled_from')) {
            $query->whereDate('enrolled_at', '>=', $enrolledFrom);
        }

        if ($enrolledTo = $request->query('enrolled_to')) {
            $query->whereDate('enrolled_at', '<=', $enrolledTo);
        }

        if ($lastLoginBefore = $request->query('last_login_before')) {
            $query->where(function ($q) use ($lastLoginBefore) {
                $q->whereNull('last_login_at')
                  ->orWhere('last_login_at', '<', $lastLoginBefore);
            });
        }

        if ($lastLoginFrom = $request->query('last_login_from')) {
            $query->whereNotNull('last_login_at')
                  ->where('last_login_at', '>=', $lastLoginFrom);
        }

        $fileName = 'users_' . now()->format('Ymd_His') . '.csv';

        $columns = [
            'id',
            'name',
            'email',
            'role',
            'status',
            'student_number',
            'grade',
            'class_name',
            'course',
            'enrolled_at',
            'left_at',
            'last_login_at',
            'created_at',
            'updated_at',
        ];

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');

            // ヘッダ行
            fputcsv($handle, $columns);

            $query->orderBy('id')->chunk(500, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->role,
                        $user->status,
                        $user->student_number,
                        $user->grade,
                        $user->class_name,
                        $user->course,
                        $user->enrolled_at?->format('Y-m-d'),
                        $user->left_at?->format('Y-m-d'),
                        $user->last_login_at?->toDateTimeString(),
                        $user->created_at?->toDateTimeString(),
                        $user->updated_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, $headers);
    }

    /**
     * ユーザーCSVインポート（既存ユーザーの一括更新専用）
     *
     * POST /api/admin/users/import
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json([
                'message' => 'CSVファイルを読み込めませんでした。',
            ], Response::HTTP_BAD_REQUEST);
        }

        $expectedHeader = [
            'id',
            'name',
            'email',
            'role',
            'status',
            'student_number',
            'grade',
            'class_name',
            'course',
            'enrolled_at',
            'left_at',
            'last_login_at',
            'created_at',
            'updated_at',
        ];

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return response()->json([
                'message' => 'CSVヘッダ行が読み取れませんでした。',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($header !== $expectedHeader) {
            fclose($handle);
            return response()->json([
                'message'         => 'CSVヘッダが想定と一致しません。',
                'expected_header' => $expectedHeader,
                'actual_header'   => $header,
            ], Response::HTTP_BAD_REQUEST);
        }

        $updatedCount        = 0;
        $skippedMissingUser  = 0;
        $skippedInvalidRow   = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && $row[0] === null) {
                continue;
            }

            if (count($row) !== count($expectedHeader)) {
                $skippedInvalidRow++;
                continue;
            }

            $data = array_combine($expectedHeader, $row);
            if ($data === false) {
                $skippedInvalidRow++;
                continue;
            }

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                $skippedInvalidRow++;
                continue;
            }

            /** @var User|null $user */
            $user = User::find($id);
            if (! $user) {
                $skippedMissingUser++;
                continue;
            }

            $user->name   = $data['name'] ?? $user->name;
            $user->email  = isset($data['email']) ? strtolower(trim($data['email'])) : $user->email;
            $user->role   = $data['role'] ?? $user->role;
            $user->status = $data['status'] ?? $user->status;

            $user->student_number = $data['student_number'] !== '' ? $data['student_number'] : null;
            $user->grade          = $data['grade'] !== '' ? $data['grade'] : null;
            $user->class_name     = $data['class_name'] !== '' ? $data['class_name'] : null;
            $user->course         = $data['course'] !== '' ? $data['course'] : null;

            $enrolledAt = trim($data['enrolled_at'] ?? '');
            $leftAt     = trim($data['left_at'] ?? '');

            $user->enrolled_at = $enrolledAt !== '' ? $enrolledAt : null;
            $user->left_at     = $leftAt !== '' ? $leftAt : null;

            // last_login_at / created_at / updated_at は CSV からは更新しない

            $user->save();
            $updatedCount++;
        }

        fclose($handle);

        return response()->json([
            'message'              => 'ユーザー情報のインポートが完了しました。',
            'updated'              => $updatedCount,
            'skipped_missing_user' => $skippedMissingUser,
            'skipped_invalid_row'  => $skippedInvalidRow,
        ], Response::HTTP_OK);
    }

    /**
     * 利用停止（suspended）
     *
     * POST /api/admin/users/{id}/suspend
     */
    public function suspend(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => '指定されたユーザーが存在しません。',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($user->status === 'deleted') {
            return response()->json([
                'message' => 'このユーザーは既に完全削除済みです。',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->status = 'suspended';
        $user->save();

        return response()->json([
            'message' => 'ユーザーを利用停止にしました。',
            'user'    => [
                'id'     => $user->id,
                'status' => $user->status,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * 退会処理（withdrawn）
     *
     * POST /api/admin/users/{id}/withdraw
     *
     * - ログイン不可
     * - left_at が未設定なら「今日」を入れる
     * - 個人情報は維持（必要に応じて後から anonymize 可能）
     */
    public function withdraw(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => '指定されたユーザーが存在しません。',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($user->status === 'deleted') {
            return response()->json([
                'message' => 'このユーザーは既に完全削除済みです。',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->status = 'withdrawn';

        if (! $user->left_at) {
            $user->left_at = now()->toDateString();
        }

        $user->save();

        return response()->json([
            'message' => 'ユーザーを退会状態にしました。',
            'user'    => [
                'id'       => $user->id,
                'status'   => $user->status,
                'left_at'  => $user->left_at?->format('Y-m-d'),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * 完全削除（個人情報の匿名化）
     *
     * POST /api/admin/users/{id}/anonymize
     *
     * - status = deleted
     * - name / email / 学籍情報を匿名化・クリア
     * - パスワードもランダム値に変更（万が一のログイン経路防止）
     * - 学習履歴など user_id を参照しているレコードはそのまま残る
     */
    public function anonymize(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => '指定されたユーザーが存在しません。',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($user->status === 'deleted') {
            return response()->json([
                'message' => 'このユーザーは既に完全削除済みです。',
            ], Response::HTTP_BAD_REQUEST);
        }

        $suffix = str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);

        $user->name            = '退会済みユーザー#' . $suffix;
        $user->email           = 'deleted+' . $user->id . '@example.invalid';
        $user->student_number  = null;
        $user->grade           = null;
        $user->class_name      = null;
        $user->course          = null;

        // パスワードもランダムな値に変更しておく
        $user->password = Hash::make(Str::random(40));

        // メール認証カラムなどがある場合は null にしておく（存在する場合のみ）
        if ($user->isFillable('email_verified_at') || array_key_exists('email_verified_at', $user->getAttributes())) {
            $user->email_verified_at = null;
        }

        $user->status = 'deleted';

        $user->save();

        return response()->json([
            'message' => 'ユーザーの個人情報を匿名化しました。',
            'user'    => [
                'id'     => $user->id,
                'status' => $user->status,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * F01_04 ユーザー編集（基本情報＋ステータス）
     *
     * PUT /api/admin/users/{id}
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => '指定されたユーザーが存在しません。',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        $email = strtolower(trim($data['email']));

        $user->name   = $data['name'];
        $user->email  = $email;
        $user->role   = $data['role'];
        $user->status = $data['status'];

        $user->save();

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role,
            'status' => $user->status,
        ], Response::HTTP_OK);
    }

    /**
     * F01_06 パスワードリセット（管理者用）
     *
     * PUT /api/admin/users/{id}/password
     */
    public function resetPassword(ResetUserPasswordRequest $request, int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => '指定されたユーザーが存在しません。',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        $user->password = $data['password'];
        $user->save();

        return response()->json([
            'message' => __('api.auth.messages.password_reset'),
        ], Response::HTTP_OK);
    }
}
