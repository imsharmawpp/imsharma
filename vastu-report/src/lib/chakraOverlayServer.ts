/**
 * Server-side Chakra Overlay generation using Sharp.
 * This file should ONLY be imported in API routes / server components.
 * 
 * Uses the same rotation logic as the client-side version but with Sharp for
 * reliable server-side image processing (e.g., for PDF generation).
 */

import { FacingDirection } from '@/types';
import { FACING_DIRECTIONS } from './constants';
import sharp from 'sharp';
import path from 'path';

/**
 * Same rotation logic as client-side.
 * rotation = (360 - facingDirectionDegrees) % 360
 */
function getChakraRotationDegrees(facingDirection: FacingDirection): number {
  const direction = FACING_DIRECTIONS.find(d => d.value === facingDirection);
  if (!direction) return 0;
  return (360 - direction.degrees) % 360;
}

/**
 * Server-side overlay creation using Sharp (Node.js).
 * Creates a composite image: floor plan + rotated chakra overlay.
 */
export async function createOverlayImageServer(
  floorPlanBuffer: Buffer,
  facingDirection: FacingDirection,
): Promise<Buffer> {
  const chakraImagePath = path.join(process.cwd(), 'public', 'chakra-overlay.png');
  
  // Get floor plan metadata
  const floorPlanMeta = await sharp(floorPlanBuffer).metadata();
  const width = floorPlanMeta.width || 800;
  const height = floorPlanMeta.height || 800;
  
  // Calculate rotation for chakra (counter-clockwise correction)
  const rotationDegrees = getChakraRotationDegrees(facingDirection);
  
  // Resize chakra to fit within floor plan (85% of smallest dimension)
  const chakraSize = Math.round(Math.min(width, height) * 0.85);
  
  // Process chakra: resize then rotate
  const chakraResized = await sharp(chakraImagePath)
    .resize(chakraSize, chakraSize, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .ensureAlpha()
    .png()
    .toBuffer();

  // Rotate the resized chakra
  const chakraRotated = await sharp(chakraResized)
    .rotate(rotationDegrees, { background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png()
    .toBuffer();
  
  // Make chakra semi-transparent (55% opacity)
  const chakraWithAlpha = await sharp(chakraRotated)
    .composite([{
      input: Buffer.from([255, 255, 255, Math.round(255 * 0.55)]),
      raw: { width: 1, height: 1, channels: 4 },
      tile: true,
      blend: 'dest-in' as const,
    }])
    .png()
    .toBuffer();
  
  // Get processed chakra dimensions (may change after rotation)
  const chakraMeta = await sharp(chakraWithAlpha).metadata();
  const cw = chakraMeta.width || chakraSize;
  const ch = chakraMeta.height || chakraSize;
  
  // Calculate position to center chakra on floor plan
  const left = Math.max(0, Math.round((width - cw) / 2));
  const top = Math.max(0, Math.round((height - ch) / 2));
  
  // Composite floor plan + chakra overlay
  const result = await sharp(floorPlanBuffer)
    .resize(width, height)
    .composite([
      {
        input: chakraWithAlpha,
        left,
        top,
        blend: 'over' as const,
      }
    ])
    .png()
    .toBuffer();
  
  return result;
}
