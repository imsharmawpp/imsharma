import { CommercialSubType, ResidentialSubType, ProblemArea, FacingDirection } from '@/types';

export const COMMERCIAL_SUB_TYPES: { value: CommercialSubType; label: string }[] = [
  { value: 'land', label: 'Land' },
  { value: 'office_space', label: 'Office Space' },
  { value: 'retail_showroom', label: 'Retail / Showroom' },
  { value: 'factory', label: 'Factory' },
  { value: 'warehouse', label: 'Warehouse' },
];

export const RESIDENTIAL_SUB_TYPES: { value: ResidentialSubType; label: string }[] = [
  { value: 'row_house_kothi', label: 'Row House / Kothi' },
  { value: 'builder_floor_apartment', label: 'Builder Floor / High-Rise Apartment' },
  { value: 'villa', label: 'Villa' },
];

export const PROBLEM_AREAS: { value: ProblemArea; label: string }[] = [
  { value: 'wealth', label: 'Wealth' },
  { value: 'health', label: 'Health' },
  { value: 'relationship_family_harmony', label: 'Relationship & Family Harmony' },
  { value: 'career', label: 'Career' },
  { value: 'mental_stress', label: 'Mental Stress' },
  { value: 'education', label: 'Education' },
  { value: 'other', label: 'Other' },
];

export const FACING_DIRECTIONS: { value: FacingDirection; label: string; degrees: number }[] = [
  { value: 'north', label: 'North', degrees: 0 },
  { value: 'northeast', label: 'North-East', degrees: 45 },
  { value: 'east', label: 'East', degrees: 90 },
  { value: 'southeast', label: 'South-East', degrees: 135 },
  { value: 'south', label: 'South', degrees: 180 },
  { value: 'southwest', label: 'South-West', degrees: 225 },
  { value: 'west', label: 'West', degrees: 270 },
  { value: 'northwest', label: 'North-West', degrees: 315 },
];

// The Chakra image always has NORTH on top.
// To align it with a floor plan where the user says "my house faces East":
// - The entrance/facing side of the plan is East
// - We need to rotate the chakra so that East aligns with the top of the plan
// - Rotation = degrees of the facing direction (clockwise from North)
// Example: If facing East (90°), rotate chakra 90° clockwise so East is on top
export const getChakraRotation = (facingDirection: FacingDirection): number => {
  const direction = FACING_DIRECTIONS.find(d => d.value === facingDirection);
  return direction ? direction.degrees : 0;
};

export const CHAKRA_IMAGE_URL = '/chakra-overlay.png';

export const MAX_PROBLEM_AREAS = 2;
export const MAX_OTHER_PROBLEM_TEXT_LENGTH = 30;

export const RECOMMENDATION_TEXT = `The outcome and effectiveness of Vastu remedies are significantly influenced by the Head of the Family or the Head of the Organisation, as their energy directly impacts the space they occupy. Additionally, real-time factors such as the physical objects placed in specific zones, the day-to-day use of the space, and on-ground realities play a critical role in determining the accuracy of any corrective measure.

While this report provides a comprehensive directional and zone-based analysis of your floor plan, a more personalised and in-depth consultation is available upon request. This includes an offline site visit by our expert team, where we assess the property in person — accounting for real-time placements, environmental factors, and individual-specific influences that a digital report alone cannot capture.

Shilaavinyaas is not merely a report-generation platform — we are a full-service Vastu Consultancy Firm with years of expertise in residential, commercial, and industrial Vastu. Our team offers end-to-end consulting services tailored to your unique needs.

To learn more or to book a personalised offline consultation, please connect with our team.`;

export const FLOOR_PLAN_ERROR_MESSAGES = {
  NOT_UPLOADED: 'Floor Plan not uploaded. Please upload a valid floor plan image to generate your report.',
  NOT_CLEAR: 'The uploaded image is not clear enough to be analysed. Please upload a higher resolution floor plan.',
  HAND_DRAWN: 'Hand-drawn plans are not supported for automated analysis. Please upload a digitally created floor plan or connect with our support team for manual analysis.',
  NOT_RECOGNIZED: 'The uploaded image was not recognised as a Floor Plan. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
  GENERIC: 'We were unable to process your floor plan. Please ensure the image is a clear, digital architectural floor plan and try again. For assistance, connect with our support team.',
};
