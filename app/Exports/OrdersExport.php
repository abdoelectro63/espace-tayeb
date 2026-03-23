<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        // يمكنك هنا اختيار الأعمدة التي تهمك فقط لمتجر Espace Tayeb
        return Order::select('id', 'customer_name', 'phone', 'total_price', 'status', 'created_at')->get();
    }

    public function headings(): array
    {
        return ['رقم الطلب', 'اسم الزبون', 'الهاتف', 'المجموع', 'الحالة', 'التاريخ'];
    }
}