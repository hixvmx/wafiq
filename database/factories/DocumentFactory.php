<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bare documents for tests (no lines, zero totals). Use SaveDocument for real content.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'quote',
            'number' => 'QT-'.fake()->unique()->numerify('####'),
            'status' => DocumentStatus::Draft,
            'client_id' => Client::factory(),
            'currency' => 'SAR',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Document $document) => $document->root_id ?? $document->update(['root_id' => $document->id]));
    }

    public function status(DocumentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status, 'sent_at' => $status === DocumentStatus::Draft ? null : now()]);
    }
}
