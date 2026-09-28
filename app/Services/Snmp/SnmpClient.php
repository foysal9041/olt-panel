<?php

namespace App\Services\Snmp;

use App\Models\NetworkSwitch;
use SNMP;
use SNMPException;

/**
 * Thin wrapper around PHP's SNMP extension: numeric OIDs, plain values,
 * and walks that return [] (instead of throwing) when a device simply
 * doesn't implement a subtree — the normal case for vendor MIBs.
 */
class SnmpClient
{
    protected SNMP $session;

    public function __construct(NetworkSwitch $switch, int $timeoutMs = 1500, int $retries = 1)
    {
        $version = $switch->snmp_version === '1' ? SNMP::VERSION_1 : SNMP::VERSION_2c;

        $this->session = new SNMP(
            $version,
            $switch->ip . ':' . $switch->snmp_port,
            $switch->community,
            $timeoutMs * 1000,
            $retries
        );

        $this->session->valueretrieval = SNMP_VALUE_PLAIN;
        $this->session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
        $this->session->quick_print = true;
        $this->session->enum_print = false;
        $this->session->exceptions_enabled = SNMP::ERRNO_ANY;
    }

    /**
     * GET several scalar OIDs. Throws SNMPException if the device
     * doesn't answer — used as the reachability check.
     *
     * @param  list<string>  $oids
     * @return array<string, string> keyed by the requested OID
     */
    public function get(array $oids): array
    {
        $raw = $this->session->get($oids);
        $out = [];

        foreach ($oids as $oid) {
            $out[$oid] = $raw['.' . $oid] ?? $raw[$oid] ?? null;
        }

        return $out;
    }

    /**
     * Walk a subtree. Keys are the index suffix after $oid ("12", "3.1").
     *
     * @return array<string, string>
     */
    public function walk(string $oid): array
    {
        try {
            $result = $this->session->walk($oid, true, 25);
        } catch (SNMPException $e) {
            // Missing subtree or end of MIB view: not an error for us.
            if ($this->isTimeout($e)) {
                throw $e;
            }

            return [];
        }

        return is_array($result) ? array_map(fn ($v) => is_string($v) ? trim($v, " \"") : $v, $result) : [];
    }

    /**
     * Walk that swallows every error — for optional vendor tables where a
     * slow or broken implementation shouldn't abort the whole poll.
     *
     * @return array<string, string>
     */
    public function tryWalk(string $oid): array
    {
        try {
            return $this->walk($oid);
        } catch (SNMPException) {
            return [];
        }
    }

    protected function isTimeout(SNMPException $e): bool
    {
        return $e->getCode() === SNMP::ERRNO_TIMEOUT || str_contains(strtolower($e->getMessage()), 'timeout');
    }

    public function close(): void
    {
        $this->session->close();
    }
}
