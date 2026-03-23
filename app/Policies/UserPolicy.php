<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * السماح برؤية القائمة
     */
    public function viewAny(User $user): bool
    {
        // إذا كان لديك حقل is_admin تأكد أنه true، وإلا استعمل true مباشرة مؤقتاً
        return true; 
    }

    /**
     * السماح بإنشاء مستخدمين جدد
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * السماح بالتعديل (هذا ما طلبته لكي يعمل زر Edit)
     */
    public function update(User $user, User $model): bool
    {
        return true;
    }

    /**
     * السماح بالحذف
     */
    public function delete(User $user, User $model): bool
    {
        return true;
    }

    // يمكنك ترك الباقي false إذا كنت لا تستخدم الـ Restore أو Force Delete
}