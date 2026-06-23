'use client';

import { FacingDirection } from '@/types';
import { FACING_DIRECTIONS } from './constants';

/**
 * Chakra Overlay Rotation Logic
 * ==============================
 * 
 * The Vastu Chakra PNG (source: https://blog.shilaavinyaas.com/uploads/news-image/88/2.png)
 * ALWAYS has NORTH at the top in its original orientation.
 * 
 * The user's floor plan is oriented with the FACING DIRECTION at the top.
 * 
 * GOAL: Overlay the chakra on the plan such that all compass zones align correctly.
 * 
 * EXAMPLE:
 * - User says "house faces East" → East is at the TOP of the plan image
 * - In reality, if East is at the top, then:
 *     North = LEFT side of the image
 *     South = RIGHT side of the image
 *     West  = BOTTOM of the image
 * - The chakra (with North on top) must be rotated so North points LEFT
 * - That means rotating the chakra 90° COUNTER-CLOCKWISE (i.e., -90° or 270° clockwise)
 * 
 * FORMULA:
 *   rotation = 360 - facingDirectionDegrees (mod 360)
 *   OR equivalently: rotation = -facingDirectionDegrees
 * 
 * Where facingDirectionDegrees is measured clockwise from North:
 *   North = 0°, East = 90°, South = 180°, West = 270°
 * 
 * Verification:
 *   - North facing (0°): rotation = 360-0 = 0° → chakra stays as-is (N on top) ✓
 *   - East facing (90°):  rotation = 360-90 = 270° CW = 90° CCW → N points left ✓
 *   - South facing (180°): rotation = 360-180 = 180° → N points down ✓
 *   - West facing (270°):  rotation = 360-270 = 90° CW → N points right ✓
 */

export function getChakraRotationDegrees(facingDirection: FacingDirection): number {
  const direction = FACING_DIRECTIONS.find(d => d.value === facingDirection);
  if (!direction) return 0;
  
  // Rotate COUNTER-CLOCKWISE by the facing direction degrees
  // In CSS/Canvas rotation (clockwise positive), this is: 360 - degrees
  const rotation = (360 - direction.degrees) % 360;
  return rotation;
}

/**
 * Creates a combined overlay image on HTML Canvas (client-side).
 * Takes the floor plan and overlays the correctly-rotated chakra on top.
 * 
 * The output image shows:
 * - Floor plan as the base layer
 * - Semi-transparent Vastu Chakra overlaid with correct directional alignment
 * - Direction labels at corners for reference
 */
export async function createOverlayImage(
  floorPlanDataUrl: string,
  facingDirection: FacingDirection,
  chakraImageUrl: string = '/chakra-overlay.png'
): Promise<string> {
  return new Promise((resolve, reject) => {
    const floorPlanImg = new Image();
    const chakraImg = new Image();
    
    let floorPlanLoaded = false;
    let chakraLoaded = false;
    
    const tryCompose = () => {
      if (!floorPlanLoaded || !chakraLoaded) return;
      
      try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        if (!ctx) {
          reject(new Error('Canvas context unavailable'));
          return;
        }
        
        // Use floor plan dimensions
        canvas.width = floorPlanImg.width;
        canvas.height = floorPlanImg.height;
        
        // Draw floor plan as base
        ctx.drawImage(floorPlanImg, 0, 0);
        
        // Calculate chakra size (90% of smallest dimension to fit well)
        const chakraSize = Math.min(canvas.width, canvas.height) * 0.85;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        
        // Get rotation (counter-clockwise correction)
        const rotationDegrees = getChakraRotationDegrees(facingDirection);
        const rotationRadians = (rotationDegrees * Math.PI) / 180;
        
        // Draw rotated chakra overlay with transparency
        ctx.save();
        ctx.globalAlpha = 0.55; // Semi-transparent overlay
        ctx.translate(centerX, centerY);
        ctx.rotate(rotationRadians);
        ctx.drawImage(
          chakraImg,
          -chakraSize / 2,
          -chakraSize / 2,
          chakraSize,
          chakraSize
        );
        ctx.restore();
        
        // Add direction labels at edges for clarity
        ctx.save();
        ctx.font = 'bold 14px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        
        // The facing direction is at the TOP of the plan
        const facingLabel = FACING_DIRECTIONS.find(d => d.value === facingDirection)?.label || '';
        
        // Top label (facing direction)
        ctx.fillStyle = '#DC2626';
        ctx.fillRect(centerX - 50, 4, 100, 22);
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText(`↑ ${facingLabel}`, centerX, 15);
        
        ctx.restore();
        
        resolve(canvas.toDataURL('image/png'));
      } catch (error) {
        reject(error);
      }
    };
    
    floorPlanImg.onload = () => {
      floorPlanLoaded = true;
      tryCompose();
    };
    
    chakraImg.onload = () => {
      chakraLoaded = true;
      tryCompose();
    };
    
    floorPlanImg.onerror = () => reject(new Error('Failed to load floor plan image'));
    chakraImg.onerror = () => reject(new Error('Failed to load chakra image'));
    
    // Set crossOrigin for chakra image (loaded from public folder)
    chakraImg.crossOrigin = 'anonymous';
    
    floorPlanImg.src = floorPlanDataUrl;
    chakraImg.src = chakraImageUrl;
  });
}
