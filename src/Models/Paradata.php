<?php

namespace Nikoleesg\Survey\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nikoleesg\Survey\Traits\BelongsToSample;
use Nikoleesg\Survey\Traits\HasTablePrefix;

class Paradata extends Model
{
    use HasTablePrefix, BelongsToSample;

    protected $guarded = [];

    public const UPSERT_KEYS = ['sample_id', 'label'];

    public function scopeOfLabel(Builder $query, string $label): void
    {
        $query->where('label', '=', $label);
    }

    public function scopeOfAddress(Builder $query): void
    {
        $query->ofLabel('Address');
    }

    public function scopeOfAddressId(Builder $query): void
    {
        $query->ofLabel('AddressId');
    }

    public function scopeOfClientInformation(Builder $query): void
    {
        $query->ofLabel('ClientInformation');
    }

    public function scopeOfDeviceId(Builder $query): void
    {
        $query->ofLabel('DeviceId');
    }

    public function scopeOfEndReason(Builder $query): void
    {
        $query->ofLabel('EndReason');
    }

    public function scopeOfInterviewEndTime(Builder $query): void
    {
        $query->ofLabel('InterviewEndTime');
    }

    public function scopeOfInterviewStartTime(Builder $query): void
    {
        $query->ofLabel('InterviewStartTime');
    }


    public function scopeOfInterviewerId(Builder $query): void
    {
        $query->ofLabel('InterviewerId');
    }

    public function scopeOfLocaleId(Builder $query): void
    {
        $query->ofLabel('LocaleId');
    }

    public function scopeOfQuota(Builder $query): void
    {
        $query->ofLabel('Quota');
    }

    public function scopeOfQuotaVariables(Builder $query): void
    {
        $query->ofLabel('QuotaVariables');
    }

    public function scopeOfSampleData(Builder $query): void
    {
        $query->ofLabel('SampleData');
    }

    public function scopeOfSamplingPointId(Builder $query): void
    {
        $query->ofLabel('SamplingPointId');
    }

    public function scopeOfSurveyETag(Builder $query): void
    {
        $query->ofLabel('SurveyETag');
    }

    public function scopeOfSurveyVersion(Builder $query): void
    {
        $query->ofLabel('SurveyVersion');
    }

    public function scopeOfTestInterview(Builder $query): void
    {
        $query->ofLabel('TestInterview');
    }

    public function scopeOfTimeZone(Builder $query): void
    {
        $query->ofLabel('TimeZone');
    }

    public function scopeOfLocationInfo(Builder $query): void
    {
        $query->ofLabel('LocationInfo');
    }

    public function scopeOfLastLocationInfo(Builder $query): void
    {
        $query->ofLabel('LastLocationInfo');
    }

    public function scopeOfInterviewQuality(Builder $query): void
    {
        $query->ofLabel('InterviewQuality');
    }
}
