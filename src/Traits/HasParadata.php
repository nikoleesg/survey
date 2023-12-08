<?php

namespace Nikoleesg\Survey\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasParadata
{
    abstract public function paradata(): HasMany;

    public function paradataOfAddress(): HasOne
    {
        return $this->paradata()->one()->ofAddress();
    }

    public function paradataOfAddressId(): HasOne
    {
        return $this->paradata()->one()->ofAddressId();
    }

    public function paradataOfClientInformation(): HasOne
    {
        return $this->paradata()->one()->ofClientInformation();
    }

    public function paradataOfDeviceId(): HasOne
    {
        return $this->paradata()->one()->ofDeviceId();
    }


    public function paradataOfEndReason(): HasOne
    {
        return $this->paradata()->one()->ofEndReason();
    }

    public function paradataOfInterviewEndTime(): HasOne
    {
        return $this->paradata()->one()->ofInterviewEndTime();
    }

    public function paradataOfInterviewStartTime(): HasOne
    {
        return $this->paradata()->one()->ofInterviewStartTime();
    }

    public function paradataOfInterviewerId(): HasOne
    {
        return $this->paradata()->one()->ofInterviewerId();
    }

    public function paradataOfLocaleId(): HasOne
    {
        return $this->paradata()->one()->ofLocaleId();
    }

    public function paradataOfQuota(): HasOne
    {
        return $this->paradata()->one()->ofQuota();
    }

    public function paradataOfQuotaVariables(): HasOne
    {
        return $this->paradata()->one()->ofQuotaVariables();
    }

    public function paradataOfSampleData(): HasOne
    {
        return $this->paradata()->one()->ofSampleData();
    }

    public function paradataOfSamplingPointId(): HasOne
    {
        return $this->paradata()->one()->ofSamplingPointId();
    }

    public function paradataOfSurveyETag(): HasOne
    {
        return $this->paradata()->one()->ofSurveyETag();
    }

    public function paradataOfSurveyVersion(): HasOne
    {
        return $this->paradata()->one()->ofSurveyVersion();
    }

    public function paradataOfTestInterview(): HasOne
    {
        return $this->paradata()->one()->ofTestInterview();
    }

    public function paradataOfTimeZone(): HasOne
    {
        return $this->paradata()->one()->ofTimeZone();
    }

    public function paradataOfLocationInfo(): HasOne
    {
        return $this->paradata()->one()->ofLocationInfo();
    }

    public function paradataOfLastLocationInfo(): HasOne
    {
        return $this->paradata()->one()->ofLastLocationInfo();
    }

    public function paradataOfInterviewQuality(): HasOne
    {
        return $this->paradata()->one()->ofInterviewQuality();
    }
    
}
