<?php

namespace Nikoleesg\Survey\Enums;

/**
 * Labels found in the paradata export. The value is stored as-is in
 * survey_paradatas.label.
 */
enum ParadataLabelEnum: string
{
    case ADDRESS = 'Address';
    case ADDRESS_ID = 'AddressId';
    case CLIENT_INFORMATION = 'ClientInformation';
    case DEVICE_ID = 'DeviceId';
    case END_REASON = 'EndReason';
    case INTERVIEW_END_TIME = 'InterviewEndTime';
    case INTERVIEW_START_TIME = 'InterviewStartTime';
    case INTERVIEWER_ID = 'InterviewerId';
    case LOCALE_ID = 'LocaleId';
    case QUOTA = 'Quota';
    case QUOTA_VARIABLES = 'QuotaVariables';
    case SAMPLE_DATA = 'SampleData';
    case SAMPLING_POINT_ID = 'SamplingPointId';
    case SURVEY_ETAG = 'SurveyETag';
    case SURVEY_VERSION = 'SurveyVersion';
    case TEST_INTERVIEW = 'TestInterview';
    case TIME_ZONE = 'TimeZone';
    case LOCATION_INFO = 'LocationInfo';
    case LAST_LOCATION_INFO = 'LastLocationInfo';
    case INTERVIEW_QUALITY = 'InterviewQuality';
}
