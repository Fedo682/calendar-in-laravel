<?php

namespace App\Enums;

/**
 * Whether an event_messages row came from the "Report conflict" action or
 * the free-text "Message admin" action. Both share one dedup rule; this
 * only distinguishes how the row is displayed and emailed.
 */
enum EventMessageType: string
{
    case Conflict = 'conflict';
    case General = 'general';
}
