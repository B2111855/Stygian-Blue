<?php

namespace App\Services;

use App\Repositories\CostumeRepository;
use mysqli;

class QuoteService
{
    private mysqli $connection;
    private CostumeRepository $costumeRepo;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
        $this->costumeRepo = new CostumeRepository($connection);
    }

    public function buildServiceQuote(int $serviceId, ?int $branchId, string $locationType, ?float $extLat, ?float $extLng): array
    {
        $servicePrice = $this->fetchServiceUnitPrice($serviceId);
        $travel = $this->computeTravelFee($branchId, $locationType, $extLat, $extLng);
        $total = $servicePrice + $travel['fee'];
        return [
            'total' => $total,
            'items' => [
                ['label' => 'Dịch vụ', 'price' => $servicePrice],
                ['label' => 'Phụ phí di chuyển', 'price' => $travel['fee']],
            ],
            'travel_fee' => $travel['fee'],
            'distance_km' => $travel['distance_km'],
        ];
    }

    /**
     * @param int[] $selectedCostumeIds Optional costume IDs user chose (subset of package costumes)
     */
    public function buildPackageQuote(int $packageId, array $selectedCostumeIds, ?int $branchId, string $locationType, ?float $extLat, ?float $extLng): array
    {
        $basePrice = $this->costumeRepo->getPackageBasePrice($packageId);
        $allCostumes = $this->costumeRepo->getCostumesForPackage($packageId);
        $selectedSet = array_flip($selectedCostumeIds);

        $costumeItems = [];
        $costumeSubtotal = 0;
        foreach ($allCostumes as $c) {
            $id = (int)$c['ID_TRANG_PHUC'];
            $base = (int)$c['GIA_THUE'];
            $discountPercent = (int)$c['DISCOUNT_PERCENT'];
            $isMandatory = (int)$c['BAT_BUOC'] === 1;

            if ($isMandatory) {
                // Included free
                $finalPrice = 0;
                $costumeItems[] = [
                    'label' => 'Trang phục (bao gồm) - ' . $c['TEN'],
                    'price' => $finalPrice,
                    'base_price' => $base,
                    'discount_percent' => 100,
                    'mandatory' => true,
                    'id' => $id,
                ];
                continue;
            }
            // Optional: only count if user selected
            if (!isset($selectedSet[$id])) {
                continue;
            }
            $finalPrice = $base;
            if ($discountPercent > 0) {
                $finalPrice = (int)round($base * (1 - $discountPercent / 100));
            }
            $costumeSubtotal += $finalPrice;
            $costumeItems[] = [
                'label' => 'Trang phục (tuỳ chọn) - ' . $c['TEN'],
                'price' => $finalPrice,
                'base_price' => $base,
                'discount_percent' => $discountPercent,
                'mandatory' => false,
                'id' => $id,
            ];
        }

        $travel = $this->computeTravelFee($branchId, $locationType, $extLat, $extLng);
        $total = $basePrice + $costumeSubtotal + $travel['fee'];

        $items = array_merge([
            ['label' => 'Gói dịch vụ', 'price' => $basePrice],
        ], $costumeItems, [
            ['label' => 'Phụ phí di chuyển', 'price' => $travel['fee']],
        ]);

        return [
            'total' => $total,
            'package_base_price' => $basePrice,
            'costume_subtotal' => $costumeSubtotal,
            'items' => $items,
            'travel_fee' => $travel['fee'],
            'distance_km' => $travel['distance_km'],
        ];
    }

    /**
     * @param int[] $costumeIds
     */
    public function buildCostumeOnlyQuote(array $costumeIds, ?int $branchId, string $locationType, ?float $extLat, ?float $extLng): array
    {
        $costumeIds = array_values(array_unique(array_filter(array_map('intval', $costumeIds), fn ($id) => $id > 0)));
        if (empty($costumeIds)) {
            return [
                'total' => 0,
                'items' => [],
                'travel_fee' => 0,
                'distance_km' => null,
            ];
        }
        $costumes = $this->costumeRepo->getCostumesByIds($costumeIds);
        $costumeMap = [];
        foreach ($costumes as $c) {
            $costumeMap[(int)$c['ID_TRANG_PHUC']] = $c;
        }
        $items = [];
        $subtotal = 0;
        foreach ($costumeIds as $id) {
            if (!isset($costumeMap[$id])) continue;
            $row = $costumeMap[$id];
            $price = (int)$row['GIA_THUE'];
            $subtotal += $price;
            $items[] = [
                'label' => 'Trang phục - ' . $row['TEN'],
                'price' => $price,
                'base_price' => $price,
                'id' => $id,
            ];
        }
        $travel = $this->computeTravelFee($branchId, $locationType, $extLat, $extLng);
        $total = $subtotal + $travel['fee'];
        $items[] = ['label' => 'Phụ phí di chuyển', 'price' => $travel['fee']];

        return [
            'total' => $total,
            'items' => $items,
            'costume_subtotal' => $subtotal,
            'travel_fee' => $travel['fee'],
            'distance_km' => $travel['distance_km'],
        ];
    }

    private function fetchServiceUnitPrice(int $serviceId): int
    {
        if ($serviceId <= 0) return 0;
        $sql = "SELECT DON_GIA FROM DON_GIA_DICH_VU WHERE ID_DV = ? ORDER BY NGAY_GIO DESC LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) return 0;
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row && isset($row['DON_GIA']) ? (int)$row['DON_GIA'] : 0;
    }

    private function computeTravelFee(?int $branchId, string $locationType, ?float $extLat, ?float $extLng): array
    {
        $distanceKm = null;
        $fee = 0;
        if ($locationType === 'external' && $branchId && $extLat !== null && $extLng !== null && is_finite($extLat) && is_finite($extLng)) {
            // Kiểm tra cột toạ độ
            $check = $this->connection->query("SHOW COLUMNS FROM CHI_NHANH LIKE 'LATITUDE'");
            $hasCoords = $check && $check->num_rows > 0;
            if ($check) { $check->free_result(); }
            if ($hasCoords) {
                $stmt = $this->connection->prepare('SELECT LATITUDE, LONGITUDE FROM CHI_NHANH WHERE ID_CN = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $branchId);
                    $stmt->execute();
                    $stmt->bind_result($bLat, $bLng);
                    if ($stmt->fetch() && $bLat !== null && $bLng !== null) {
                        $distanceKm = $this->haversineKm((float)$bLat, (float)$bLng, (float)$extLat, (float)$extLng);
                        $fee = $this->calcTravelFee($distanceKm);
                    }
                    $stmt->close();
                }
            }
        }
        return [
            'fee' => $fee,
            'distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    private function calcTravelFee(float $distanceKm): int
    {
        if (!is_finite($distanceKm)) return 0;
        if ($distanceKm <= 20) return 0;
        return (int)ceil($distanceKm - 20) * 5000;
    }
}
