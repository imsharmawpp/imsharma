// ===== Property Types =====
export type PropertyCategory = 'commercial' | 'residential';

export type CommercialSubType = 'land' | 'office_space' | 'retail_showroom' | 'factory' | 'warehouse';
export type ResidentialSubType = 'row_house_kothi' | 'builder_floor_apartment' | 'villa';

export type PropertySubType = CommercialSubType | ResidentialSubType;

// ===== Problem Areas =====
export type ProblemArea = 
  | 'wealth'
  | 'health'
  | 'relationship_family_harmony'
  | 'career'
  | 'mental_stress'
  | 'education'
  | 'other';

// ===== Facing Direction =====
export type FacingDirection = 'north' | 'south' | 'east' | 'west' | 'northeast' | 'northwest' | 'southeast' | 'southwest';

// ===== Questionnaire Data =====
export interface QuestionnaireData {
  propertyCategory: PropertyCategory;
  propertySubType: PropertySubType;
  sizeInSqFt?: number;
  problemAreas: ProblemArea[]; // max 2
  otherProblemText?: string; // max 30 characters
  name: string;
  mobileNumber: string;
  email?: string;
}

// ===== Upload & Direction Data =====
export interface UploadData {
  floorPlanFile: File | null;
  floorPlanPreview: string | null;
  facingDirection: FacingDirection;
}

// ===== OTP Verification =====
export interface OtpState {
  sent: boolean;
  verified: boolean;
  otp: string;
  timer: number;
}

// ===== Report Data =====
export interface ReportData {
  questionnaire: QuestionnaireData;
  upload: UploadData;
  overlayImageUrl: string; // combined chakra + floor plan
  generatedAt: string;
  reportId: string;
}

// ===== Floor Plan Validation =====
export interface FloorPlanValidationResult {
  isValid: boolean;
  errorMessage?: string;
  confidence?: number;
}

// ===== API Responses =====
export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
}

// ===== Lead Data =====
export interface LeadData {
  id: string;
  questionnaire: QuestionnaireData;
  createdAt: string;
  status: 'captured' | 'report_generated' | 'payment_completed';
}
