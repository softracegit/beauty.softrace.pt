<?php

namespace App\Services;

use App\Models\CrmSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingReminderSettings
{
    public const KEY = CrmSetting::KEY_REMINDERS_SETTINGS;

    /**
     * @return array{
     *   sms_day_before_enabled: bool,
     *   sms_same_day_enabled: bool,
     *   email_day_before_enabled: bool,
     *   email_same_day_enabled: bool,
     *   day_before_send_start_hour: int,
     *   day_before_send_end_hour: int,
     *   min_minutes_after_created: int,
     *   same_day_lead_min_minutes: int,
     *   same_day_lead_max_minutes: int,
     *   same_day_send_start_minutes: int,
     *   same_day_send_end_minutes: int,
     *   sms_day_before_max_per_run: int,
     *   sms_same_day_max_per_run: int,
     *   email_max_per_run: int
     * }
     */
    public static function defaults(): array
    {
        $leadMin = max(1, (int) config('booking.sms_same_day_lead_min_minutes', 120));
        $leadMax = max($leadMin, (int) config('booking.sms_same_day_lead_max_minutes', 180));
        $windowStart = max(0, min(24 * 60 - 1, (int) config('booking.sms_same_day_send_start_minutes', 8 * 60 + 30)));
        $windowEnd = max($windowStart + 1, min(24 * 60, (int) config('booking.sms_same_day_send_end_minutes', 18 * 60)));

        return [
            'sms_day_before_enabled' => (bool) config('booking.sms_reminder_enabled', true),
            'sms_same_day_enabled' => (bool) config('booking.sms_same_day_enabled', true),
            'email_day_before_enabled' => (bool) config('booking.email_reminder_enabled', true),
            'email_same_day_enabled' => (bool) config('booking.email_same_day_enabled', true),
            'day_before_send_start_hour' => max(0, min(23, (int) config('booking.sms_reminder_day_before_send_start_hour', 9))),
            'day_before_send_end_hour' => max(1, min(24, (int) config('booking.sms_reminder_day_before_send_end_hour', 20))),
            'min_minutes_after_created' => max(0, (int) config('booking.sms_reminder_min_minutes_after_created', 45)),
            'same_day_lead_min_minutes' => $leadMin,
            'same_day_lead_max_minutes' => $leadMax,
            'same_day_send_start_minutes' => $windowStart,
            'same_day_send_end_minutes' => $windowEnd,
            'sms_day_before_max_per_run' => max(1, (int) config('booking.sms_reminder_max_per_run', 3)),
            'sms_same_day_max_per_run' => max(1, (int) config('booking.sms_same_day_max_per_run', 3)),
            'email_max_per_run' => max(1, (int) config('booking.email_reminder_max_per_run', 10)),
        ];
    }

    /**
     * @return array{
     *   sms_day_before_enabled: bool,
     *   sms_same_day_enabled: bool,
     *   email_day_before_enabled: bool,
     *   email_same_day_enabled: bool,
     *   day_before_send_start_hour: int,
     *   day_before_send_end_hour: int,
     *   min_minutes_after_created: int,
     *   same_day_lead_min_minutes: int,
     *   same_day_lead_max_minutes: int,
     *   same_day_send_start_minutes: int,
     *   same_day_send_end_minutes: int,
     *   sms_day_before_max_per_run: int,
     *   sms_same_day_max_per_run: int,
     *   email_max_per_run: int
     * }
     */
    public static function forStore(?int $storeId = null): array
    {
        $defaults = self::defaults();
        $raw = CrmSetting::getString(self::KEY, '', $storeId);
        if ($raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return self::normalize(array_merge($defaults, $decoded));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   sms_day_before_enabled: bool,
     *   sms_same_day_enabled: bool,
     *   email_day_before_enabled: bool,
     *   email_same_day_enabled: bool,
     *   day_before_send_start_hour: int,
     *   day_before_send_end_hour: int,
     *   min_minutes_after_created: int,
     *   same_day_lead_min_minutes: int,
     *   same_day_lead_max_minutes: int,
     *   same_day_send_start_minutes: int,
     *   same_day_send_end_minutes: int,
     *   sms_day_before_max_per_run: int,
     *   sms_same_day_max_per_run: int,
     *   email_max_per_run: int
     * }
     */
    public static function validateAndNormalize(array $input, ?int $storeId = null): array
    {
        $existing = self::forStore($storeId);
        $sameDayStartHour = (int) ($input['same_day_send_start_hour'] ?? self::minutesToStartHour($existing['same_day_send_start_minutes']));
        $sameDayEndHour = (int) ($input['same_day_send_end_hour'] ?? self::minutesToEndHour($existing['same_day_send_end_minutes']));
        $leadMinHours = (float) ($input['same_day_lead_min_hours'] ?? self::minutesToHours($existing['same_day_lead_min_minutes']));
        $leadMaxHours = (float) ($input['same_day_lead_max_hours'] ?? self::minutesToHours($existing['same_day_lead_max_minutes']));

        $formCheck = Validator::make([
            'same_day_lead_min_hours' => $leadMinHours,
            'same_day_lead_max_hours' => $leadMaxHours,
            'same_day_send_start_hour' => $sameDayStartHour,
            'same_day_send_end_hour' => $sameDayEndHour,
        ], [
            'same_day_lead_min_hours' => ['numeric', 'min:0.5', 'max:24'],
            'same_day_lead_max_hours' => ['numeric', 'min:0.5', 'max:24'],
            'same_day_send_start_hour' => ['integer', 'min:0', 'max:23'],
            'same_day_send_end_hour' => ['integer', 'min:1', 'max:24'],
        ]);
        $formCheck->after(function ($v) use ($leadMinHours, $leadMaxHours, $sameDayStartHour, $sameDayEndHour): void {
            if ($leadMaxHours < $leadMinHours) {
                $v->errors()->add('same_day_lead_max_hours', 'A antecedência máxima deve ser ≥ à mínima.');
            }
            if ($sameDayEndHour <= $sameDayStartHour) {
                $v->errors()->add('same_day_send_end_hour', 'A hora de fim do próprio dia deve ser depois da hora de início.');
            }
        });
        if ($formCheck->fails()) {
            throw new ValidationException($formCheck);
        }

        $payload = [
            'sms_day_before_enabled' => ! empty($input['sms_day_before_enabled']),
            'sms_same_day_enabled' => ! empty($input['sms_same_day_enabled']),
            'email_day_before_enabled' => ! empty($input['email_day_before_enabled']),
            'email_same_day_enabled' => ! empty($input['email_same_day_enabled']),
            'day_before_send_start_hour' => (int) ($input['day_before_send_start_hour'] ?? 9),
            'day_before_send_end_hour' => (int) ($input['day_before_send_end_hour'] ?? 20),
            'min_minutes_after_created' => (int) ($input['min_minutes_after_created'] ?? 45),
            'same_day_lead_min_minutes' => self::hoursToMinutes($leadMinHours),
            'same_day_lead_max_minutes' => self::hoursToMinutes($leadMaxHours),
            'same_day_send_start_minutes' => max(0, min(23, $sameDayStartHour)) * 60,
            'same_day_send_end_minutes' => max(1, min(24, $sameDayEndHour)) * 60,
            // Limites técnicos: mantêm-se no .env / valor já guardado (não expostos na UI).
            'sms_day_before_max_per_run' => $existing['sms_day_before_max_per_run'],
            'sms_same_day_max_per_run' => $existing['sms_same_day_max_per_run'],
            'email_max_per_run' => $existing['email_max_per_run'],
        ];

        $validator = Validator::make($payload, [
            'sms_day_before_enabled' => ['boolean'],
            'sms_same_day_enabled' => ['boolean'],
            'email_day_before_enabled' => ['boolean'],
            'email_same_day_enabled' => ['boolean'],
            'day_before_send_start_hour' => ['integer', 'min:0', 'max:23'],
            'day_before_send_end_hour' => ['integer', 'min:1', 'max:24'],
            'min_minutes_after_created' => ['integer', 'min:0', 'max:1440'],
            'same_day_lead_min_minutes' => ['integer', 'min:1', 'max:1440'],
            'same_day_lead_max_minutes' => ['integer', 'min:1', 'max:1440'],
            'same_day_send_start_minutes' => ['integer', 'min:0', 'max:1439'],
            'same_day_send_end_minutes' => ['integer', 'min:1', 'max:1440'],
            'sms_day_before_max_per_run' => ['integer', 'min:1', 'max:100'],
            'sms_same_day_max_per_run' => ['integer', 'min:1', 'max:100'],
            'email_max_per_run' => ['integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return self::normalize($payload);
    }

    /**
     * @param  array{
     *   sms_day_before_enabled: bool,
     *   sms_same_day_enabled: bool,
     *   email_day_before_enabled: bool,
     *   email_same_day_enabled: bool,
     *   day_before_send_start_hour: int,
     *   day_before_send_end_hour: int,
     *   min_minutes_after_created: int,
     *   same_day_lead_min_minutes: int,
     *   same_day_lead_max_minutes: int,
     *   same_day_send_start_minutes: int,
     *   same_day_send_end_minutes: int,
     *   sms_day_before_max_per_run: int,
     *   sms_same_day_max_per_run: int,
     *   email_max_per_run: int
     * }  $settings
     */
    public static function save(array $settings, ?int $storeId = null): void
    {
        CrmSetting::setString(
            self::KEY,
            json_encode(self::normalize($settings), JSON_UNESCAPED_UNICODE),
            $storeId
        );
    }

    /**
     * @param  array{day_before_send_start_hour: int, day_before_send_end_hour: int}  $settings
     */
    public static function isWithinDayBeforeSendWindow(array $settings, int $hourLocal): bool
    {
        $start = (int) $settings['day_before_send_start_hour'];
        $end = (int) $settings['day_before_send_end_hour'];
        if ($end <= $start) {
            return true;
        }

        return $hourLocal >= $start && $hourLocal < $end;
    }

    /**
     * @param  array{same_day_send_start_minutes: int, same_day_send_end_minutes: int}  $settings
     */
    public static function isWithinSameDaySendWindow(array $settings, int $minutesNow): bool
    {
        $start = (int) $settings['same_day_send_start_minutes'];
        $end = max($start + 1, (int) $settings['same_day_send_end_minutes']);

        return $minutesNow >= $start && $minutesNow < $end;
    }

    public static function minutesToTime(int $minutes): string
    {
        $minutes = max(0, min(24 * 60, $minutes));
        if ($minutes >= 24 * 60) {
            return '24:00';
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function minutesToHours(int $minutes): float|int
    {
        $hours = round(max(0, $minutes) / 60, 1);
        if (abs($hours - (int) $hours) < 0.001) {
            return (int) $hours;
        }

        return $hours;
    }

    public static function hoursToMinutes(float $hours): int
    {
        return max(1, (int) round($hours * 60));
    }

    public static function minutesToStartHour(int $minutes): int
    {
        return max(0, min(23, intdiv(max(0, $minutes), 60)));
    }

    public static function minutesToEndHour(int $minutes): int
    {
        $minutes = max(1, min(24 * 60, $minutes));
        if ($minutes % 60 === 0) {
            return max(1, min(24, intdiv($minutes, 60)));
        }

        return max(1, min(24, (int) ceil($minutes / 60)));
    }

    public static function timeToMinutes(string $time): int
    {
        $time = trim($time);
        if ($time === '24:00') {
            return 24 * 60;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return 0;
        }
        $h = max(0, min(24, (int) $m[1]));
        $i = max(0, min(59, (int) $m[2]));
        if ($h === 24) {
            return 24 * 60;
        }

        return $h * 60 + $i;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   sms_day_before_enabled: bool,
     *   sms_same_day_enabled: bool,
     *   email_day_before_enabled: bool,
     *   email_same_day_enabled: bool,
     *   day_before_send_start_hour: int,
     *   day_before_send_end_hour: int,
     *   min_minutes_after_created: int,
     *   same_day_lead_min_minutes: int,
     *   same_day_lead_max_minutes: int,
     *   same_day_send_start_minutes: int,
     *   same_day_send_end_minutes: int,
     *   sms_day_before_max_per_run: int,
     *   sms_same_day_max_per_run: int,
     *   email_max_per_run: int
     * }
     */
    private static function normalize(array $data): array
    {
        $leadMin = max(1, (int) ($data['same_day_lead_min_minutes'] ?? 120));
        $leadMax = max($leadMin, (int) ($data['same_day_lead_max_minutes'] ?? 180));
        $windowStart = max(0, min(24 * 60 - 1, (int) ($data['same_day_send_start_minutes'] ?? 510)));
        $windowEnd = max($windowStart + 1, min(24 * 60, (int) ($data['same_day_send_end_minutes'] ?? 1080)));

        return [
            'sms_day_before_enabled' => (bool) ($data['sms_day_before_enabled'] ?? true),
            'sms_same_day_enabled' => (bool) ($data['sms_same_day_enabled'] ?? true),
            'email_day_before_enabled' => (bool) ($data['email_day_before_enabled'] ?? true),
            'email_same_day_enabled' => (bool) ($data['email_same_day_enabled'] ?? true),
            'day_before_send_start_hour' => max(0, min(23, (int) ($data['day_before_send_start_hour'] ?? 9))),
            'day_before_send_end_hour' => max(1, min(24, (int) ($data['day_before_send_end_hour'] ?? 20))),
            'min_minutes_after_created' => max(0, (int) ($data['min_minutes_after_created'] ?? 45)),
            'same_day_lead_min_minutes' => $leadMin,
            'same_day_lead_max_minutes' => $leadMax,
            'same_day_send_start_minutes' => $windowStart,
            'same_day_send_end_minutes' => $windowEnd,
            'sms_day_before_max_per_run' => max(1, (int) ($data['sms_day_before_max_per_run'] ?? 3)),
            'sms_same_day_max_per_run' => max(1, (int) ($data['sms_same_day_max_per_run'] ?? 3)),
            'email_max_per_run' => max(1, (int) ($data['email_max_per_run'] ?? 10)),
        ];
    }
}
