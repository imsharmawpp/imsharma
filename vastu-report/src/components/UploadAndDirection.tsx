'use client';

import React, { useState, useCallback, useRef } from 'react';
import { UploadData, FacingDirection, FloorPlanValidationResult } from '@/types';
import { FACING_DIRECTIONS, FLOOR_PLAN_ERROR_MESSAGES } from '@/lib/constants';
import { validateFloorPlan } from '@/lib/floorPlanValidator';

interface UploadAndDirectionProps {
  onComplete: (data: UploadData) => void;
  onBack: () => void;
}

export default function UploadAndDirection({ onComplete, onBack }: UploadAndDirectionProps) {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [facingDirection, setFacingDirection] = useState<FacingDirection | ''>('');
  const [validationResult, setValidationResult] = useState<FloorPlanValidationResult | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const fileInputRef = useRef<HTMLInputElement>(null);

  // STRICT GATE: Can only proceed if validation explicitly passed
  const isFloorPlanValid = validationResult !== null && validationResult.isValid === true;

  const handleFileSelect = useCallback(async (selectedFile: File) => {
    setFile(selectedFile);
    setValidationResult(null); // Reset validation
    setErrors({});
    
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      setPreview(e.target?.result as string);
    };
    reader.readAsDataURL(selectedFile);
    
    // Validate the floor plan - THIS IS THE GATE
    setIsValidating(true);
    try {
      const result = await validateFloorPlan(selectedFile);
      setValidationResult(result);
      if (!result.isValid) {
        setErrors(prev => ({ ...prev, file: result.errorMessage || FLOOR_PLAN_ERROR_MESSAGES.GENERIC }));
      }
    } catch {
      setValidationResult({ isValid: false, errorMessage: FLOOR_PLAN_ERROR_MESSAGES.GENERIC });
      setErrors(prev => ({ ...prev, file: FLOOR_PLAN_ERROR_MESSAGES.GENERIC }));
    } finally {
      setIsValidating(false);
    }
  }, []);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    
    const droppedFile = e.dataTransfer.files[0];
    if (droppedFile && droppedFile.type.startsWith('image/')) {
      handleFileSelect(droppedFile);
    } else if (droppedFile) {
      setErrors(prev => ({ ...prev, file: FLOOR_PLAN_ERROR_MESSAGES.NOT_RECOGNIZED }));
    }
  }, [handleFileSelect]);

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => setIsDragging(false);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      handleFileSelect(selectedFile);
    }
  };

  const handleRemoveFile = () => {
    setFile(null);
    setPreview(null);
    setValidationResult(null);
    setErrors({});
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleSubmit = () => {
    const newErrors: Record<string, string> = {};
    
    // STRICT CHECK 1: No file uploaded at all
    if (!file) {
      newErrors.file = FLOOR_PLAN_ERROR_MESSAGES.NOT_UPLOADED;
    }
    // STRICT CHECK 2: File uploaded but validation failed or not completed
    else if (!isFloorPlanValid) {
      newErrors.file = validationResult?.errorMessage || FLOOR_PLAN_ERROR_MESSAGES.GENERIC;
    }
    
    // STRICT CHECK 3: Direction must be selected
    if (!facingDirection) {
      newErrors.direction = 'Please select the facing direction of your property';
    }
    
    setErrors(newErrors);
    
    // ABSOLUTE BLOCK: Do NOT proceed if any errors
    if (Object.keys(newErrors).length > 0) return;
    
    // Double-check the gate one final time
    if (!isFloorPlanValid || !file || !preview) {
      setErrors({ file: FLOOR_PLAN_ERROR_MESSAGES.GENERIC });
      return;
    }
    
    onComplete({
      floorPlanFile: file,
      floorPlanPreview: preview,
      facingDirection: facingDirection as FacingDirection,
    });
  };

  return (
    <div className="max-w-5xl mx-auto">
      {/* Header */}
      <div className="text-center mb-8">
        <h2 className="text-2xl font-bold text-gray-900">Upload Floor Plan & Select Direction</h2>
        <p className="mt-2 text-gray-600">Upload a clear, digital floor plan and specify the facing direction of your property</p>
      </div>

      {/* Split Layout: LEFT = Upload, RIGHT = Direction */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        {/* ===== LEFT PANEL: Upload Section ===== */}
        <div className="space-y-4">
          <h3 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <span className="w-8 h-8 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold">1</span>
            Upload Floor Plan
          </h3>
          
          {!preview ? (
            <div
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onClick={() => fileInputRef.current?.click()}
              className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all min-h-[320px] flex flex-col items-center justify-center ${
                isDragging
                  ? 'border-amber-500 bg-amber-50'
                  : 'border-gray-300 hover:border-amber-400 hover:bg-amber-50/50'
              }`}
            >
              <div className="text-5xl mb-4">📐</div>
              <p className="text-gray-700 font-medium mb-2">
                Drag & drop your floor plan here
              </p>
              <p className="text-gray-400 text-sm mb-4">or click to browse files</p>
              <p className="text-xs text-gray-400">
                Supported formats: PNG, JPG (Max 10MB)
              </p>
              <p className="text-xs text-amber-600 mt-2 font-medium">
                Only clear, digital architectural floor plans are accepted
              </p>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/jpg"
                onChange={handleInputChange}
                className="hidden"
              />
            </div>
          ) : (
            <div className="relative border-2 border-gray-200 rounded-xl overflow-hidden">
              <img
                src={preview}
                alt="Floor plan preview"
                className="w-full h-auto max-h-[380px] object-contain bg-gray-50"
              />
              
              {/* Validation In-Progress Overlay */}
              {isValidating && (
                <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                  <div className="bg-white rounded-lg px-6 py-4 flex items-center gap-3 shadow-xl">
                    <div className="animate-spin w-5 h-5 border-2 border-amber-500 border-t-transparent rounded-full"></div>
                    <span className="text-gray-700 font-medium">Analysing floor plan...</span>
                  </div>
                </div>
              )}
              
              {/* Validation Success Badge */}
              {isFloorPlanValid && !isValidating && (
                <div className="absolute top-3 right-3 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 shadow-lg">
                  <span>✓</span> Valid Floor Plan
                </div>
              )}

              {/* Validation Failed Badge */}
              {validationResult && !validationResult.isValid && !isValidating && (
                <div className="absolute top-3 right-3 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1 shadow-lg">
                  <span>✕</span> Invalid
                </div>
              )}
              
              {/* Remove button */}
              <button
                onClick={handleRemoveFile}
                className="absolute top-3 left-3 bg-red-500 text-white w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-lg"
                title="Remove file"
              >
                ✕
              </button>
            </div>
          )}
          
          {/* Validation Error Message - PROMINENT */}
          {errors.file && (
            <div className="bg-red-50 border-2 border-red-300 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <span className="text-red-500 text-2xl flex-shrink-0">⚠️</span>
                <div>
                  <p className="text-red-800 font-semibold text-sm mb-1">Cannot Generate Report</p>
                  <p className="text-red-700 text-sm">{errors.file}</p>
                  <p className="text-red-500 text-xs mt-2">
                    Need help? <a href="#" className="underline font-semibold hover:text-red-700">Connect with our support team</a>
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Requirements Info Box */}
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-gray-600 text-xs font-medium mb-1">Floor Plan Requirements:</p>
            <ul className="text-gray-500 text-xs space-y-1">
              <li>• Must be a clear, digital/CAD-generated floor plan</li>
              <li>• Minimum resolution: 300 x 300 pixels</li>
              <li>• Hand-drawn plans are not supported</li>
              <li>• Random images or photos will be rejected</li>
            </ul>
          </div>
        </div>

        {/* ===== RIGHT PANEL: Direction Selection ===== */}
        <div className="space-y-4">
          <h3 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <span className="w-8 h-8 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold">2</span>
            Select Facing Direction
          </h3>
          
          <p className="text-sm text-gray-500">
            Which direction does the <strong>main entrance</strong> of your property face?
          </p>

          {/* Compass Direction Selector */}
          <div className="relative w-full aspect-square max-w-[300px] mx-auto">
            {/* Compass background */}
            <div className="absolute inset-0 rounded-full border-4 border-gray-200 bg-gradient-to-b from-gray-50 to-white shadow-inner">
              {/* Center dot */}
              <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-amber-500 rounded-full shadow-md z-10"></div>
              
              {/* Cross lines for reference */}
              <div className="absolute top-1/2 left-[10%] right-[10%] h-px bg-gray-200"></div>
              <div className="absolute left-1/2 top-[10%] bottom-[10%] w-px bg-gray-200"></div>
              
              {/* Direction buttons arranged in compass layout */}
              {FACING_DIRECTIONS.map((dir) => {
                const angle = dir.degrees - 90; // CSS: 0° is right, so subtract 90 for North=top
                const radians = (angle * Math.PI) / 180;
                const radius = 40; // percentage from center
                const x = 50 + radius * Math.cos(radians);
                const y = 50 + radius * Math.sin(radians);
                const isSelected = facingDirection === dir.value;
                
                // Use short labels for compass: N, NE, E, SE, S, SW, W, NW
                const shortLabel = dir.value === 'north' ? 'N' 
                  : dir.value === 'northeast' ? 'NE'
                  : dir.value === 'east' ? 'E'
                  : dir.value === 'southeast' ? 'SE'
                  : dir.value === 'south' ? 'S'
                  : dir.value === 'southwest' ? 'SW'
                  : dir.value === 'west' ? 'W'
                  : 'NW';
                
                return (
                  <button
                    key={dir.value}
                    type="button"
                    onClick={() => {
                      setFacingDirection(dir.value);
                      setErrors(prev => {
                        const next = { ...prev };
                        delete next.direction;
                        return next;
                      });
                    }}
                    className={`absolute w-12 h-12 -ml-6 -mt-6 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                      isSelected
                        ? 'bg-amber-500 text-white shadow-lg scale-115 ring-4 ring-amber-200'
                        : 'bg-white text-gray-600 border-2 border-gray-200 hover:border-amber-400 hover:text-amber-600 hover:scale-105'
                    }`}
                    style={{ left: `${x}%`, top: `${y}%` }}
                    title={dir.label}
                  >
                    {shortLabel}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Selected direction display */}
          {facingDirection && (
            <div className="text-center bg-amber-50 border border-amber-200 rounded-lg py-3 px-4">
              <p className="text-amber-700 font-semibold text-sm">
                Selected: <span className="text-amber-900">{FACING_DIRECTIONS.find(d => d.value === facingDirection)?.label} Facing</span>
              </p>
            </div>
          )}
          
          {errors.direction && (
            <p className="text-red-500 text-sm text-center font-medium">{errors.direction}</p>
          )}

          {/* Direction help text */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p className="text-blue-700 text-xs leading-relaxed">
              <strong>How to determine facing direction:</strong> Stand at your main entrance door and face outward (towards the road/outside). The compass direction you are facing is your property&apos;s facing direction.
            </p>
          </div>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex gap-4 mt-10 pt-6 border-t border-gray-100">
        <button
          type="button"
          onClick={onBack}
          className="px-6 py-4 border-2 border-gray-200 text-gray-600 rounded-xl font-medium hover:bg-gray-50 transition-colors"
        >
          ← Back
        </button>
        <button
          type="button"
          onClick={handleSubmit}
          disabled={isValidating || !isFloorPlanValid || !facingDirection}
          className="flex-1 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-semibold text-lg hover:from-amber-600 hover:to-orange-600 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
        >
          {isValidating ? 'Analysing Floor Plan...' : !isFloorPlanValid && file ? 'Floor Plan Invalid — Cannot Proceed' : 'Proceed to Payment →'}
        </button>
      </div>
    </div>
  );
}
