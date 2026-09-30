<?php

namespace App\Repositories;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PatientRepository implements PatientRepositoryInterface
{
    /** Filter keys understood by query() (also used to build query strings). */
    public const FILTERS = ['search', 'category', 'sex', 'is_active', 'year_level', 'section', 'program_strand', 'sort', 'dir'];

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    public function query(array $filters): Builder
    {
        $query = Patient::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('patient_number', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        foreach (['year_level', 'section', 'program_strand'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['is_active'] ?? '') === 'archived') {
            // Archived = soft-deleted patients (restorable from the list).
            $query->onlyTrashed();
        } elseif (isset($filters['is_active']) && in_array((string) $filters['is_active'], ['0', '1'], true)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['sex'])) {
            $query->where('sex', $filters['sex']);
        }

        $sortBy  = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['dir']  ?? 'desc';
        $allowed = ['last_name', 'first_name', 'patient_number', 'created_at', 'category', 'year_level'];

        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }
        $query->orderBy('id', 'desc');

        return $query;
    }

    public function findById(int $id): ?Patient
    {
        return Patient::find($id);
    }

    public function create(array $data): Patient
    {
        return Patient::create($data);
    }

    public function update(Patient $patient, array $data): Patient
    {
        $patient->update($data);
        return $patient->fresh();
    }

    public function delete(Patient $patient): bool
    {
        return $patient->delete();
    }
}
