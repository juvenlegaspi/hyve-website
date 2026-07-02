@extends('layouts.admin')

@section('content')
    <style>
        .admin-room-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 1.1rem;
        }

        .admin-room-modal.hidden {
            display: none !important;
        }

        .admin-room-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(18, 24, 21, 0.38);
            backdrop-filter: blur(10px);
        }

        .admin-room-modal__card {
            position: relative;
            z-index: 1;
            width: min(38rem, 100%);
            max-height: calc(100vh - 2.2rem);
            overflow-y: auto;
            padding: 1.75rem 1.85rem 1.5rem;
            border: 1px solid rgba(17, 52, 44, 0.08);
            border-radius: 1.6rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 28px 70px rgba(17, 28, 24, 0.18);
        }

        .admin-room-modal__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        .admin-room-modal__title,
        .admin-room-modal__subtitle,
        .admin-room-modal__label {
            margin: 0;
        }

        .admin-room-modal__title {
            color: #202823;
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .admin-room-modal__subtitle {
            margin-top: 0.22rem;
            color: #777f78;
            font-size: 0.75rem;
            line-height: 1.45;
        }

        .admin-room-modal__close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 0;
            border-radius: 999px;
            background: #f4f4f1;
            color: #6f766f;
            font-size: 1.15rem;
            line-height: 1;
            cursor: pointer;
            transition: background 160ms ease, color 160ms ease;
        }

        .admin-room-modal__close:hover {
            background: #eceee8;
            color: #2f3b34;
        }

        .admin-room-modal__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem 0.8rem;
        }

        .admin-room-modal__meta {
            display: grid;
            gap: 0.32rem;
            padding: 0.9rem 0.95rem;
            border: 1px solid #e4e8df;
            border-radius: 0.95rem;
            background: #f8faf6;
        }

        .admin-room-modal__meta-value {
            color: #203026;
            font-size: 0.82rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .admin-room-modal__hint {
            margin-top: 0.15rem;
            color: #8b928b;
            font-size: 0.71rem;
            line-height: 1.45;
        }

        .admin-room-modal__field {
            display: grid;
            gap: 0.4rem;
        }

        .admin-room-modal__field--full {
            grid-column: 1 / -1;
        }

        .admin-room-modal__label {
            color: #b2afa8;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .admin-room-modal__control,
        .admin-room-modal__textarea {
            width: 100%;
            border: 1px solid #dde3da;
            border-radius: 0.95rem;
            background: #fbfbf8;
            color: #1f2822;
            font-size: 0.8rem;
            outline: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
            transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;
        }

        .admin-room-modal__control {
            min-height: 2.85rem;
            padding: 0.8rem 0.95rem;
        }

        .admin-room-modal__textarea {
            min-height: 5.4rem;
            padding: 0.85rem 0.95rem;
            resize: none;
        }

        .admin-room-modal__control:focus,
        .admin-room-modal__textarea:focus {
            border-color: rgba(68, 121, 59, 0.4);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(68, 121, 59, 0.08);
        }

        .admin-room-modal__control[readonly],
        .admin-room-modal__control:disabled {
            color: #2a322c;
            opacity: 1;
        }

        .admin-room-modal__footer {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(17, 52, 44, 0.08);
        }

        .admin-room-modal__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 3rem;
            padding: 0.85rem 1.2rem;
            border-radius: 0.95rem;
            font-size: 0.8rem;
            font-weight: 700;
            transition: background 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .admin-room-modal__button:hover {
            transform: translateY(-1px);
        }

        .admin-room-modal__button--ghost {
            border: 1px solid #dfe4dc;
            background: #fff;
            color: #57655d;
        }

        .admin-room-modal__button--ghost:hover {
            background: #f7f9f4;
        }

        .admin-room-modal__button--primary {
            margin-left: auto;
            min-width: 11rem;
            border: 1px solid transparent;
            background: #44793b;
            color: #fff;
        }

        .admin-room-modal__button--primary:hover {
            background: #396733;
        }

        @media (max-width: 760px) {
            .admin-room-modal {
                padding: 0.7rem;
            }

            .admin-room-modal__card {
                max-height: calc(100vh - 1.4rem);
                padding: 1rem 1rem 0.95rem;
                border-radius: 1.15rem;
            }

            .admin-room-modal__grid {
                grid-template-columns: 1fr;
            }

            .admin-room-modal__field--full {
                grid-column: auto;
            }

            .admin-room-modal__footer {
                flex-wrap: wrap;
            }

            .admin-room-modal__button,
            .admin-room-modal__button--primary {
                width: 100%;
                min-width: 0;
                margin-left: 0;
            }
        }

        .admin-rooms__table-wrap {
            position: relative;
            z-index: 0;
            max-height: 36.5rem;
            overflow-y: auto;
            overflow-x: auto;
        }

        .admin-rooms__table-wrap::-webkit-scrollbar {
            width: 0.7rem;
            height: 0.7rem;
        }

        .admin-rooms__table-wrap::-webkit-scrollbar-track {
            background: #f3f5ef;
        }

        .admin-rooms__table-wrap::-webkit-scrollbar-thumb {
            border: 2px solid #f3f5ef;
            border-radius: 999px;
            background: #bec9ba;
        }

        .admin-rooms__table-wrap table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .admin-rooms__table-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #fcfdfb;
            box-shadow: inset 0 -1px 0 #edf1ea;
        }

        .admin-rooms__action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.38rem;
            min-height: 2rem;
            min-width: 4.9rem;
            padding: 0.45rem 0.72rem;
            border: 1px solid #d8dfd3;
            border-radius: 999px;
            background: linear-gradient(180deg, #ffffff 0%, #f4f8ef 100%);
            color: #355a3b;
            font-size: 0.71rem;
            font-weight: 700;
            line-height: 1;
            box-shadow: 0 8px 20px rgba(41, 70, 48, 0.08);
            transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .admin-rooms__action:hover {
            border-color: #b8caad;
            background: linear-gradient(180deg, #ffffff 0%, #eef6e7 100%);
            box-shadow: 0 10px 24px rgba(41, 70, 48, 0.12);
            transform: translateY(-1px);
        }

        .admin-rooms__action svg {
            flex-shrink: 0;
        }

        .admin-rooms__add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 2.65rem;
            padding: 0.72rem 1.05rem;
            border: 1px solid rgba(68, 121, 59, 0.14);
            border-radius: 999px;
            background: linear-gradient(180deg, #4c8441 0%, #3e7335 100%);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            box-shadow: 0 14px 28px rgba(52, 91, 44, 0.16);
            transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }

        .admin-rooms__add-button:hover {
            background: linear-gradient(180deg, #538f47 0%, #3a6b31 100%);
            box-shadow: 0 16px 32px rgba(52, 91, 44, 0.2);
            transform: translateY(-1px);
        }

        .admin-rooms__add-button svg {
            flex-shrink: 0;
        }
    </style>

    @php
        $currentPage = 1;
        $activeRoomModal = (string) request('room', '');
        $roomsBaseUrl = route('admin.sections.rooms');
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[1.45rem] font-semibold tracking-[-0.04em] text-[#132320]">Rooms</h1>
            <p class="mt-1 text-[0.78rem] text-[#a9a293]">{{ $rooms->count() }} rooms - manage availability and pricing</p>
        </div>

        <button type="button" class="admin-rooms__add-button">
            <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M8 3.25v9.5M3.25 8h9.5"></path>
            </svg>
            <span>Add room</span>
        </button>
    </div>

    <section class="mt-4 overflow-hidden rounded-[0.95rem] border border-[#e0e4db] bg-white">
        <div class="admin-rooms__table-wrap">
            <table class="min-w-full text-left">
                <thead class="border-b border-[#edf1ea] bg-[#fcfdfb] text-[0.76rem] font-bold uppercase tracking-[0.12em] text-[#b3ada1]">
                    <tr>
                        <th class="w-[34%] px-5 py-3">Room</th>
                        <th class="w-[16%] py-3">Type</th>
                        <th class="w-[32%] py-3">Setup</th>
                        <th class="w-[10%] py-3">Status</th>
                        <th class="w-[8%] min-w-[74px] py-3 pr-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef1ea]">
                    @forelse ($rooms as $room)
                        @php
                            $roomType = match ($room->layoutGroup()) {
                                'featured' => 'Meeting',
                                'private' => 'Private',
                                default => 'Shared',
                            };
                        @endphp

                        <tr class="transition hover:bg-[#fbfcf8]">
                            <td class="px-5 py-3">
                                <p class="text-[0.86rem] font-semibold tracking-[-0.02em] text-[#132320]">{{ $room->room_name }}</p>
                            </td>
                            <td class="py-3 text-[0.78rem] text-[#5f6f67]">{{ $roomType }}</td>
                            <td class="py-3 text-[0.78rem] text-[#5f6f67]">{{ $room->mappedSpaceLabel() }}</td>
                            <td class="py-3">
                                <span class="@if ((int) $room->status === 0) bg-[#eef8df] text-[#3f6a34] @else bg-[#f8efdd] text-[#9a7832] @endif inline-flex rounded-full px-2.5 py-0.5 text-[0.62rem] font-semibold">
                                    {{ (int) $room->status === 0 ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-center">
                                <a
                                    href="{{ route('admin.sections.rooms', ['room' => $room->id]) }}"
                                    data-room-modal-open="room-modal-{{ $room->id }}"
                                    class="admin-rooms__action"
                                >
                                    <span>Edit</span>
                                    <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.2">
                                        <path d="M3.25 12.75h2.1l6.05-6.05-2.1-2.1-6.05 6.05v2.1Z"></path>
                                        <path d="m8.95 4.95 2.1 2.1M9.65 3.2l2.1 2.1"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-7 text-center text-[0.78rem] text-[#8f897e]">No rooms found yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-3 flex items-center justify-between gap-3">
        <p class="text-[0.72rem] text-[#9b9488]">
            Showing all {{ $rooms->count() }} rooms in one scrollable list
        </p>
        <p class="text-[0.7rem] font-medium text-[#a39c8f]">
            Scroll down to view more rooms
        </p>
    </div>

        @foreach ($rooms as $room)
        @php
            $roomType = match ($room->layoutGroup()) {
                'featured' => 'Meeting',
                'private' => 'Private',
                default => 'Shared',
            };
            $shouldOpen = (string) old('room_id') === (string) $room->id || $activeRoomModal === (string) $room->id;
        @endphp

        <div
            id="room-modal-{{ $room->id }}"
            data-room-modal
            class="@if (! $shouldOpen) hidden @endif fixed inset-0 z-[140] flex items-center justify-center p-5"
        >
            <a href="{{ $roomsBaseUrl }}" class="admin-room-modal__backdrop absolute inset-0" data-room-modal-close></a>

            <div class="admin-room-modal__card relative z-[141]">
                <div class="admin-room-modal__top">
                    <div>
                        <h2 class="admin-room-modal__title">Edit Room</h2>
                        <p class="admin-room-modal__subtitle">Fill in room details and pricing</p>
                    </div>

                    <a href="{{ $roomsBaseUrl }}" data-room-modal-close class="admin-room-modal__close" aria-label="Close room modal">
                        <span aria-hidden="true">&times;</span>
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.rooms.update', $room) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="room_id" value="{{ $room->id }}">
                    <input type="hidden" name="page" value="{{ $currentPage }}">

                    <div class="admin-room-modal__grid">
                        <div class="admin-room-modal__field admin-room-modal__field--full">
                            <label class="admin-room-modal__label">Room name</label>
                            <input
                                type="text"
                                name="room_name"
                                value="{{ old('room_id') == $room->id ? old('room_name') : $room->room_name }}"
                                class="admin-room-modal__control"
                            >
                        </div>

                        <div class="admin-room-modal__field">
                            <label class="admin-room-modal__label">Type</label>
                            <div class="admin-room-modal__meta">
                                <span class="admin-room-modal__meta-value">{{ $roomType }}</span>
                            </div>
                        </div>

                        <div class="admin-room-modal__field">
                            <label class="admin-room-modal__label">Setup</label>
                            <div class="admin-room-modal__meta">
                                <span class="admin-room-modal__meta-value">{{ $room->mappedSpaceLabel() }}</span>
                            </div>
                        </div>

                        <div class="admin-room-modal__field admin-room-modal__field--full">
                            <label class="admin-room-modal__label">Status</label>
                            <select
                                name="status"
                                class="admin-room-modal__control"
                            >
                                <option value="0" @selected((string) (old('room_id') == $room->id ? old('status', (string) $room->status) : $room->status) === '0')>Active</option>
                                <option value="1" @selected((string) (old('room_id') == $room->id ? old('status', (string) $room->status) : $room->status) === '1')>Inactive</option>
                            </select>
                            <p class="admin-room-modal__hint">Active rooms can be booked. Inactive rooms are hidden from the booking flow.</p>
                        </div>

                        <div class="admin-room-modal__field admin-room-modal__field--full">
                            <label class="admin-room-modal__label">Description</label>
                            <textarea
                                name="description"
                                rows="3"
                                placeholder="Short description of this room..."
                                class="admin-room-modal__textarea"
                            >{{ old('room_id') == $room->id ? old('description') : $room->description }}</textarea>
                            <p class="admin-room-modal__hint">This updates the room description saved in `hyve_rooms`. Pricing is managed separately from rate cards.</p>
                        </div>
                    </div>

                    <div class="admin-room-modal__footer">
                        <a href="{{ $roomsBaseUrl }}" data-room-modal-close class="admin-room-modal__button admin-room-modal__button--ghost">
                                Cancel
                        </a>
                        <button type="submit" class="admin-room-modal__button admin-room-modal__button--primary">
                            Save room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
