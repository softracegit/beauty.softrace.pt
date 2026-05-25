@php
    use App\Models\CrmSetting;

    $policyText = $bookingCancellationPolicyNotice
        ?? CrmSetting::bookingCancellationPolicyNoticeText($storeId ?? null);
@endphp
<p class="small text-muted mb-0">{{ $policyText }}</p>
