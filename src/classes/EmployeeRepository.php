<?php
/**
 * src/classes/EmployeeRepository.php
 * Employee profiles, one JSON file per employee in data/employees/ (see specs/employee.schema.json).
 */

require_once __DIR__ . '/JsonStore.php';
require_once __DIR__ . '/ResourceProvider.php';

class EmployeeRepository {
    private const ID_PATTERN = '/^emp_[0-9a-z_]+$/';

    private string $dir;

    public function __construct() {
        $this->dir = ResourceProvider::dataPath('employees');
    }

    public static function isValidId(string $id): bool {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    public static function newId(): string {
        return 'emp_' . time() . '_' . random_int(100, 999);
    }

    private function path(string $id): string {
        return $this->dir . '/' . $id . '.json';
    }

    /**
     * All employees keyed by ID, sorted by family name then given name.
     */
    public function getAll(): array {
        $employees = [];
        foreach (glob($this->dir . '/emp_*.json') ?: [] as $file) {
            $employee = JsonStore::read($file);
            $id = basename($file, '.json');
            if ($employee !== null && self::isValidId($id)) {
                $employees[$id] = $employee + ['id' => $id];
            }
        }
        uasort($employees, function (array $a, array $b) {
            return strcasecmp(($a['familyName'] ?? '') . ' ' . ($a['givenName'] ?? ''), ($b['familyName'] ?? '') . ' ' . ($b['givenName'] ?? ''));
        });
        return $employees;
    }

    /**
     * Merges $fields into the stored profile so attributes this form does not edit
     * (certificates, businessAttributes, ...) are kept.
     */
    public function save(string $id, array $fields, string $updatedBy): bool {
        if (!self::isValidId($id)) {
            return false;
        }
        return JsonStore::update($this->path($id), function (array $employee) use ($id, $fields, $updatedBy) {
            return array_merge($employee, $fields, [
                'id' => $id,
                'updatedAt' => date(DATE_ATOM),
                'updatedBy' => $updatedBy,
            ]);
        });
    }

    public function delete(string $id): bool {
        return self::isValidId($id) && JsonStore::delete($this->path($id));
    }
}
