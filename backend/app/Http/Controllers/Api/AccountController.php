<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePhotoRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Throwable;

/** "My account": the signed-in person's own profile, picture and password. */
class AccountController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** PATCH /auth/me. Changing the email needs `current_password` (see UpdateProfileRequest). */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->safe()->only(['first_name', 'last_name', 'email']));

        return new UserResource($user);
    }

    /** POST /auth/me/photo (multipart `photo`). The old picture is deleted; the new URL differs, so no stale cache. */
    public function storePhoto(UpdatePhotoRequest $request, ImageStore $images): UserResource
    {
        $user = $request->user();
        $new = $images->storeAvatar($request->file('photo'));
        $old = $user->photo_path;

        try {
            $user->photo_path = $new->path;

            if (! $user->save()) {
                throw new RuntimeException('Unable to update the account photo.');
            }
        } catch (Throwable $e) {
            $images->delete($new->path, $new->disk);
            throw $e;
        }

        $images->delete($old, 'public');

        return new UserResource($user);
    }

    /** DELETE /auth/me/photo -> the user with photo_url null. */
    public function destroyPhoto(Request $request, ImageStore $images): UserResource
    {
        $user = $request->user();
        $old = $user->photo_path;
        $user->photo_path = null;

        if (! $user->save()) {
            throw new RuntimeException('Unable to remove the account photo.');
        }

        $images->delete($old, 'public');

        return new UserResource($user);
    }

    /**
     * PUT /auth/me/password. After a change, every OTHER device is signed out (if somebody else had the old
     * password, they lose access); this device stays signed in.
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->id))
            ->delete();

        return response()->noContent();
    }
}
