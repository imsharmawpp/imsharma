import { NextRequest, NextResponse } from 'next/server';
import { FLOOR_PLAN_ERROR_MESSAGES } from '@/lib/constants';

/**
 * Server-side floor plan validation API.
 * 
 * This endpoint validates uploaded images to ensure they are valid floor plans.
 * In production, integrate with:
 * - Google Cloud Vision API (label detection for "floor plan", "architectural drawing")
 * - AWS Rekognition Custom Labels (trained on floor plan dataset)
 * - Custom ML model
 * 
 * Current implementation: Basic heuristic checks.
 */

export async function POST(request: NextRequest) {
  try {
    const formData = await request.formData();
    const file = formData.get('floorPlan') as File | null;
    
    // Check 1: No file uploaded
    if (!file) {
      return NextResponse.json({
        success: false,
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_UPLOADED,
        shouldGenerateReport: false,
      }, { status: 400 });
    }

    // Check 2: Not an image
    if (!file.type.startsWith('image/')) {
      return NextResponse.json({
        success: false,
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
        shouldGenerateReport: false,
      }, { status: 400 });
    }

    // Check 3: File too small (likely not a real floor plan)
    if (file.size < 10000) { // Less than 10KB
      return NextResponse.json({
        success: false,
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_CLEAR,
        shouldGenerateReport: false,
      }, { status: 400 });
    }

    // Check 4: File too large
    if (file.size > 10 * 1024 * 1024) { // More than 10MB
      return NextResponse.json({
        success: false,
        isValid: false,
        errorMessage: 'File size exceeds 10MB limit. Please upload a smaller image.',
        shouldGenerateReport: false,
      }, { status: 400 });
    }

    // Check 5: Image analysis using Sharp (server-side)
    const buffer = Buffer.from(await file.arrayBuffer());
    
    try {
      const sharp = (await import('sharp')).default;
      const metadata = await sharp(buffer).metadata();
      
      // Minimum resolution check
      if (!metadata.width || !metadata.height || metadata.width < 300 || metadata.height < 300) {
        return NextResponse.json({
          success: false,
          isValid: false,
          errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_CLEAR,
          shouldGenerateReport: false,
        });
      }

      // Analyze image statistics for basic validation
      const stats = await sharp(buffer).stats();
      
      // Check if image is mostly blank (single color)
      const channels = stats.channels;
      const isBlank = channels.every(ch => ch.stdev < 10); // Very low standard deviation = blank
      
      if (isBlank) {
        return NextResponse.json({
          success: false,
          isValid: false,
          errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
          shouldGenerateReport: false,
        });
      }

      // Basic structural analysis
      // Floor plans typically have:
      // - High contrast (walls vs space)
      // - Moderate entropy (not random noise, not blank)
      // - Rectangular features
      
      const avgStdev = channels.reduce((sum, ch) => sum + ch.stdev, 0) / channels.length;
      
      // If standard deviation is extremely high, it might be a photo/random image, not a floor plan
      // Floor plans tend to have moderate stdev (30-120 range typically)
      // Very high = noisy photo; Very low = too simple/blank
      const hasReasonableStructure = avgStdev > 15 && avgStdev < 150;
      
      if (!hasReasonableStructure) {
        return NextResponse.json({
          success: false,
          isValid: false,
          errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
          shouldGenerateReport: false,
        });
      }

      // TODO: In production, add AI-based classification here
      // Example with Google Vision API:
      // const vision = require('@google-cloud/vision');
      // const client = new vision.ImageAnnotatorClient();
      // const [result] = await client.labelDetection(buffer);
      // const labels = result.labelAnnotations;
      // const isFloorPlan = labels.some(l => 
      //   l.description.toLowerCase().includes('floor plan') ||
      //   l.description.toLowerCase().includes('blueprint') ||
      //   l.description.toLowerCase().includes('architectural')
      // );

      // If all checks pass, the image is likely a valid floor plan
      return NextResponse.json({
        success: true,
        isValid: true,
        shouldGenerateReport: true,
        confidence: 0.75, // Heuristic confidence
        metadata: {
          width: metadata.width,
          height: metadata.height,
          format: metadata.format,
        },
      });

    } catch (sharpError) {
      console.error('Image analysis error:', sharpError);
      return NextResponse.json({
        success: false,
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.GENERIC,
        shouldGenerateReport: false,
      }, { status: 500 });
    }
    
  } catch (error) {
    console.error('Validation error:', error);
    return NextResponse.json({
      success: false,
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.GENERIC,
      shouldGenerateReport: false,
    }, { status: 500 });
  }
}
