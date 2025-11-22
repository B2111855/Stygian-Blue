<?php
namespace App\Services;

use App\Repositories\ServiceRepository; // reuse existing logic

class DeletionPolicyService {
    private \mysqli $conn;
    private ServiceRepository $repo;

    public function __construct(\mysqli $conn) {
        $this->conn = $conn;
        $this->repo = new ServiceRepository($conn);
    }

    /**
     * Return array of blocking reasons for retiring/purging a service.
     * Structure: [ ['code'=>string,'message'=>string], ... ]
     */
    public function serviceDeletionCheck(int $serviceId): array {
        return $this->repo->deletionBlockingReasons($serviceId);
    }

    /** Can retire if no blocking reasons (financial history still allows retire). */
    public function canRetire(int $serviceId): bool {
        $reasons = $this->serviceDeletionCheck($serviceId);
        // financial_history is advisory (still allow retire) -> filter out advisory codes
        $blocking = array_filter($reasons, fn($r)=>$r['code'] !== 'financial_history');
        return empty($blocking);
    }

    /** Can purge only if no appointments, no active packages and no financial history. */
    public function canPurge(int $serviceId): bool {
        $reasons = $this->serviceDeletionCheck($serviceId);
        return empty($reasons);
    }

    /** Convert reasons to human readable combined string. */
    public function formatReasons(array $reasons): string {
        return implode(' | ', array_map(fn($r)=>$r['message'], $reasons));
    }
}
