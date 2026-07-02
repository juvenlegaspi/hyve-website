<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HyveRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoomController extends Controller
{
    public function update(Request $request, HyveRoom $room): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'integer', Rule::in([$room->id])],
            'room_name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('hyve_rooms', 'room_name')->ignore($room->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        $room->update([
            'room_name' => $validated['room_name'],
            'description' => $validated['description'] ?: null,
            'status' => (int) $validated['status'],
        ]);

        return redirect()
            ->route('admin.sections.rooms', ['page' => $request->integer('page', 1)])
            ->with('admin_success', "{$room->room_name} was updated successfully.");
    }
}
