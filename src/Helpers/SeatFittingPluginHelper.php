<?php

namespace Raikia\SeatMarketSeeding\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SeatFittingPluginHelper
{
    protected static string $oldDoctrineModel = 'Denngarr\Seat\Fitting\Models\Doctrine';

    protected static string $oldFittingModel = 'Denngarr\Seat\Fitting\Models\Fitting';

    protected static string $doctrineModel = 'CryptaTech\Seat\Fitting\Models\Doctrine';

    protected static string $fittingModel = 'CryptaTech\Seat\Fitting\Models\Fitting';

    public static function pluginIsAvailable(): bool
    {
        return self::modelsExist(self::$oldDoctrineModel, self::$oldFittingModel)
            || self::modelsExist(self::$doctrineModel, self::$fittingModel);
    }

    public static function isOldVersion(): bool
    {
        return self::modelsExist(self::$oldDoctrineModel, self::$oldFittingModel);
    }

    public static function searchDoctrines(string $query, int $limit = 15): Collection
    {
        $model = self::doctrineModel();

        return $model
            ? $model::query()->where('name', 'like', '%' . self::escapeLike($query) . '%')->orderBy('name')->limit($limit)->get()
            : collect();
    }

    public static function searchFittings(string $query, int $limit = 15): Collection
    {
        $model = self::fittingModel();

        return $model
            ? $model::query()->where('name', 'like', '%' . self::escapeLike($query) . '%')->orderBy('name')->limit($limit)->get()
            : collect();
    }

    public static function getDoctrineWithFittings(int $id): ?Model
    {
        $model = self::doctrineModel();

        return $model
            ? $model::with('fittings.items.type', 'fittings.ship')->find($id)
            : null;
    }

    public static function getFittingWithItems(int $id): ?Model
    {
        $model = self::fittingModel();

        return $model
            ? $model::with('items.type', 'ship')->find($id)
            : null;
    }

    private static function doctrineModel(): ?string
    {
        if (self::isOldVersion()) {
            return self::$oldDoctrineModel;
        }

        return class_exists(self::$doctrineModel) ? self::$doctrineModel : null;
    }

    private static function fittingModel(): ?string
    {
        if (self::isOldVersion()) {
            return self::$oldFittingModel;
        }

        return class_exists(self::$fittingModel) ? self::$fittingModel : null;
    }

    private static function modelsExist(string $doctrineModel, string $fittingModel): bool
    {
        return class_exists($doctrineModel)
            && class_exists($fittingModel)
            && Schema::hasTable((new $doctrineModel)->getTable())
            && Schema::hasTable((new $fittingModel)->getTable());
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
