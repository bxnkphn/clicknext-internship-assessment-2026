<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\User;

class TransactionController extends Controller
{
    public function manage(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'type' => 'required|in:deposit,withdraw',
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);
        $amount = $request->amount;
        $type = $request->type;

        if ($type == 'withdraw' && $user->balance < $amount) {
            return response()->json(['message' => 'ยอดเงินในบัญชีไม่เพียงพอ'], 400);
        }

        DB::beginTransaction();

        try {
            Transaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
            ]);

            if ($type == 'deposit') {
                $user->balance += $amount;
            } else {
                $user->balance -= $amount;
            }
            $user->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'ทำรายการสำเร็จ',
                'balance' => $user->balance,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    public function history(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $transactions = Transaction::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'type' => 'required|in:deposit,withdraw',
        ]);

        DB::beginTransaction();
        try {
            $transaction = Transaction::findOrFail($id);
            $user = User::find($transaction->user_id);

            if ($transaction->type == 'deposit') {
                $user->balance -= $transaction->amount;
            } else {
                $user->balance += $transaction->amount;
            }

            $transaction->amount = $request->amount;
            $transaction->type = $request->type;
            $transaction->save();

            if ($transaction->type == 'deposit') {
                $user->balance += $transaction->amount;
            } else {
                if ($user->balance < $transaction->amount) {
                    throw new \Exception("ยอดเงินคงเหลือไม่เพียงพอสำหรับการแก้ไขรายการนี้");
                }
                $user->balance -= $transaction->amount;
            }
            
            $user->save();
            DB::commit();

            return response()->json([
                'status' => 'success', 
                'message' => 'แก้ไขข้อมูลสำเร็จ',
                'balance' => $user->balance
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $transaction = Transaction::findOrFail($id);
            $user = User::find($transaction->user_id);

            if ($transaction->type == 'deposit') {
                $user->balance -= $transaction->amount;
            } else {
                $user->balance += $transaction->amount; 
            }

            if ($user->balance < 0) {
                throw new \Exception("ไม่สามารถลบรายการนี้ได้ เนื่องจากจะทำให้ยอดเงินติดลบ");
            }

            $transaction->delete();
            $user->save();
            
            DB::commit();

            return response()->json([
                'status' => 'success', 
                'message' => 'ลบรายการสำเร็จ',
                'balance' => $user->balance
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}