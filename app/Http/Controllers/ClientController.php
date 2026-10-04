<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\Phone;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $type = in_array($request->query('type'), Client::TYPES, true) ? $request->query('type') : null;

        $clients = Client::search($request->query('q'))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Client $client) => $client->toFormArray());

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => ['q' => (string) $request->query('q', ''), 'type' => $type],
            'phoneCodes' => Phone::CODES,
        ]);
    }

    /** Quick search for pickers (the document editor). */
    public function search(Request $request): JsonResponse
    {
        $clients = Client::search($request->query('q'))->orderBy('name')->limit(10)->get();

        return response()->json(['data' => $clients->map(fn (Client $client) => $client->toFormArray())]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create([...$this->validated($request), 'owner_id' => $request->user()->id]);

        return back()->with('success', __('ui.clients.created'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return back()->with('success', __('ui.clients.updated'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return back()->with('success', __('ui.clients.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))) ?: null]);

        $data = $request->validate([
            'type' => ['required', Rule::in(Client::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_code' => ['nullable', Rule::in(array_values(Phone::CODES))],
            'phone_number' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) use ($request) {
                if (Phone::normalize($request->input('phone_code'), $value) === null) {
                    $fail(__('ui.clients.invalid_phone'));
                }
            }],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            ...collect($data)->except('phone_code', 'phone_number')->all(),
            'phone' => Phone::normalize($data['phone_code'] ?? null, $data['phone_number'] ?? null),
        ];
    }
}
