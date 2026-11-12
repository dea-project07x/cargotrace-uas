<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Http\Request;

class InvalidStatusTransitionException extends DomainException
{
    /**
     * @param array<int, string> $allowed
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $subject = 'status',
        public readonly array $allowed = [],
    ) {
        $hint = $allowed === [] ? 'No further transitions are allowed.' : 'Allowed: '.implode(', ', $allowed).'.';
        parent::__construct("Invalid {$subject} transition from {$from} to {$to}. {$hint}");
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => [$this->subject => [$this->getMessage()]],
            ], 422);
        }

        return back()->withErrors([$this->subject => $this->getMessage()]);
    }
}
