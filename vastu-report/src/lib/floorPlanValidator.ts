import { FloorPlanValidationResult } from '@/types';
import { FLOOR_PLAN_ERROR_MESSAGES } from './constants';

/**
 * Validates whether an uploaded image is a valid floor plan.
 * 
 * Validation checks:
 * 1. File exists and is an image
 * 2. Image has sufficient resolution (minimum 300x300px)
 * 3. Image is not predominantly a single color (blank/invalid)
 * 4. Image has characteristics of a floor plan (lines, structure)
 * 5. Image is not hand-drawn (too rough/irregular lines)
 * 
 * For production: integrate with an AI image classification service
 * (e.g., Google Vision API, AWS Rekognition, or custom ML model)
 */

interface ImageAnalysis {
  width: number;
  height: number;
  hasStructure: boolean;
  isBlank: boolean;
  isHandDrawn: boolean;
  confidence: number;
}

export async function validateFloorPlan(file: File | null): Promise<FloorPlanValidationResult> {
  // Check 1: File exists
  if (!file) {
    return {
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_UPLOADED,
    };
  }

  // Check 2: File is an image
  if (!file.type.startsWith('image/')) {
    return {
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
    };
  }

  // Check 3: File size (too small = likely not a floor plan)
  if (file.size < 10000) { // Less than 10KB
    return {
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_CLEAR,
    };
  }

  // Check 4: Analyze image content
  try {
    const analysis = await analyzeImage(file);
    
    if (analysis.width < 300 || analysis.height < 300) {
      return {
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_CLEAR,
      };
    }

    if (analysis.isBlank) {
      return {
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
      };
    }

    if (analysis.isHandDrawn) {
      return {
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.HAND_DRAWN,
      };
    }

    if (!analysis.hasStructure) {
      return {
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED,
      };
    }

    if (analysis.confidence < 0.5) {
      return {
        isValid: false,
        errorMessage: FLOOR_PLAN_ERROR_MESSAGES.GENERIC,
      };
    }

    return {
      isValid: true,
      confidence: analysis.confidence,
    };
  } catch {
    return {
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.GENERIC,
    };
  }
}

/**
 * Analyzes an image to determine if it's a valid floor plan.
 * Uses canvas-based analysis for client-side validation.
 * 
 * In production, this would call a backend API with AI/ML capabilities.
 */
async function analyzeImage(file: File): Promise<ImageAnalysis> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(file);

    img.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        if (!ctx) {
          reject(new Error('Canvas context unavailable'));
          return;
        }

        // Scale down for analysis
        const maxDim = 500;
        const scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const pixels = imageData.data;

        // Analyze pixel distribution
        let totalBrightness = 0;
        let edgePixels = 0;
        let colorVariance = 0;
        const colorCounts: Record<string, number> = {};
        
        for (let i = 0; i < pixels.length; i += 4) {
          const r = pixels[i];
          const g = pixels[i + 1];
          const b = pixels[i + 2];
          
          const brightness = (r + g + b) / 3;
          totalBrightness += brightness;
          
          // Quantize color for distribution analysis
          const qr = Math.floor(r / 32);
          const qg = Math.floor(g / 32);
          const qb = Math.floor(b / 32);
          const key = `${qr}-${qg}-${qb}`;
          colorCounts[key] = (colorCounts[key] || 0) + 1;
        }

        const totalPixels = pixels.length / 4;
        const avgBrightness = totalBrightness / totalPixels;
        
        // Edge detection (simple Sobel-like)
        for (let y = 1; y < canvas.height - 1; y++) {
          for (let x = 1; x < canvas.width - 1; x++) {
            const idx = (y * canvas.width + x) * 4;
            const idxLeft = (y * canvas.width + (x - 1)) * 4;
            const idxRight = (y * canvas.width + (x + 1)) * 4;
            const idxUp = ((y - 1) * canvas.width + x) * 4;
            const idxDown = ((y + 1) * canvas.width + x) * 4;
            
            const gx = Math.abs(
              (pixels[idxRight] + pixels[idxRight + 1] + pixels[idxRight + 2]) -
              (pixels[idxLeft] + pixels[idxLeft + 1] + pixels[idxLeft + 2])
            );
            const gy = Math.abs(
              (pixels[idxDown] + pixels[idxDown + 1] + pixels[idxDown + 2]) -
              (pixels[idxUp] + pixels[idxUp + 1] + pixels[idxUp + 2])
            );
            
            if (gx + gy > 100) edgePixels++;
          }
        }

        const edgeRatio = edgePixels / totalPixels;
        
        // Color distribution analysis
        const uniqueColors = Object.keys(colorCounts).length;
        const maxColorCount = Math.max(...Object.values(colorCounts));
        const dominantColorRatio = maxColorCount / totalPixels;

        // Calculate variance
        const colorValues = Object.values(colorCounts);
        const mean = colorValues.reduce((a, b) => a + b, 0) / colorValues.length;
        colorVariance = colorValues.reduce((acc, val) => acc + Math.pow(val - mean, 2), 0) / colorValues.length;

        // Determine characteristics
        const isBlank = dominantColorRatio > 0.95 || uniqueColors < 5;
        
        // Floor plans have moderate edge density (lines/walls)
        // Too many irregular edges = hand-drawn
        // Good edge ratio for floor plans: 0.05 - 0.4
        const hasStructure = edgeRatio > 0.03 && edgeRatio < 0.5;
        
        // Hand-drawn detection: high color variance with low unique colors
        // and irregular edge patterns
        const isHandDrawn = edgeRatio > 0.3 && uniqueColors < 20 && colorVariance > 10000;
        
        // Confidence scoring
        let confidence = 0;
        if (hasStructure && !isBlank && !isHandDrawn) {
          confidence = 0.6; // Base confidence for structural images
          if (edgeRatio > 0.05 && edgeRatio < 0.3) confidence += 0.2; // Good edge ratio
          if (uniqueColors > 10 && uniqueColors < 100) confidence += 0.1; // Reasonable color palette
          if (img.width > 500 && img.height > 500) confidence += 0.1; // Good resolution
        }

        resolve({
          width: img.width,
          height: img.height,
          hasStructure,
          isBlank,
          isHandDrawn,
          confidence: Math.min(confidence, 1),
        });
      } finally {
        URL.revokeObjectURL(url);
      }
    };

    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Failed to load image'));
    };

    img.src = url;
  });
}

/**
 * Server-side validation placeholder.
 * In production, this would use AI services for more accurate detection.
 */
export async function validateFloorPlanServer(imageBuffer: Buffer): Promise<FloorPlanValidationResult> {
  // TODO: Integrate with AI image classification API
  // Options:
  // 1. Google Cloud Vision API - label detection
  // 2. AWS Rekognition - custom labels
  // 3. Custom trained model for floor plan detection
  
  // For now, basic size check
  if (imageBuffer.length < 10000) {
    return {
      isValid: false,
      errorMessage: FLOOR_PLAN_ERROR_MESSAGES.NOT_CLEAR,
    };
  }

  return {
    isValid: true,
    confidence: 0.7,
  };
}
