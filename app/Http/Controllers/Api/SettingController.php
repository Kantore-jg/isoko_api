<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Cache::remember('settings.payload', now()->addMinutes(5), function (): array {
            return [
                'market' => Market::query()->first(),
                'settings' => SystemSetting::query()
                    ->orderBy('key')
                    ->get(),
            ];
        }));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'market' => ['nullable', 'array'],
            'market.code' => ['sometimes', 'string', 'max:50', Rule::unique('market', 'code')->ignore(optional(Market::query()->first())->id)],
            'market.name' => ['sometimes', 'string', 'max:150'],
            'market.description' => ['nullable', 'string'],
            'market.address' => ['nullable', 'string', 'max:255'],
            'market.commune' => ['nullable', 'string', 'max:150'],
            'market.province' => ['nullable', 'string', 'max:150'],
            'market.phone' => ['nullable', 'string', 'max:30'],
            'market.email' => ['nullable', 'email', 'max:150'],
            'market.logo' => ['nullable', 'string', 'max:255'],
            'market.status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
            'settings' => ['nullable', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['required'],
            'settings.*.type' => ['required', 'string', 'max:50'],
            'settings.*.description' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $market = null;

        DB::transaction(function () use (&$market, $data, $user): void {
            if (array_key_exists('market', $data) && $data['market'] !== null) {
                $market = Market::query()->firstOrFail();
                $market->update(array_filter($data['market'], static fn ($value) => $value !== null));
            }

            foreach ($data['settings'] ?? [] as $settingData) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => is_array($settingData['value']) ? json_encode($settingData['value']) : (string) $settingData['value'],
                        'type' => $settingData['type'],
                        'description' => $settingData['description'] ?? null,
                        'updated_by' => $user?->id,
                    ]
                );
            }
        });

        Cache::forget('settings.payload');

        return response()->json([
            'message' => 'Paramètres mis à jour.',
            'data' => [
                'market' => $market?->fresh() ?? Market::query()->first(),
                'settings' => SystemSetting::query()->orderBy('key')->get(),
            ],
        ]);
    }
}
