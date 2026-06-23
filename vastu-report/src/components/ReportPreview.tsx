'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { QuestionnaireData, UploadData } from '@/types';
import { FACING_DIRECTIONS, PROBLEM_AREAS, RECOMMENDATION_TEXT } from '@/lib/constants';
import { createOverlayImage } from '@/lib/chakraOverlay';

interface ReportPreviewProps {
  questionnaire: QuestionnaireData;
  upload: UploadData;
  onBack: () => void;
}

export default function ReportPreview({ questionnaire, upload, onBack }: ReportPreviewProps) {
  const [overlayImage, setOverlayImage] = useState<string | null>(null);
  const [isGenerating, setIsGenerating] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const generateOverlay = useCallback(async () => {
    // STRICT: Do not generate if no valid floor plan
    if (!upload.floorPlanPreview) {
      setError('No valid floor plan available. Report cannot be generated without a verified floor plan.');
      setIsGenerating(false);
      return;
    }

    try {
      const result = await createOverlayImage(
        upload.floorPlanPreview,
        upload.facingDirection
      );
      setOverlayImage(result);
    } catch (err) {
      console.error('Overlay generation failed:', err);
      setError('Failed to generate the Vastu Chakra overlay. Please try again.');
    } finally {
      setIsGenerating(false);
    }
  }, [upload.floorPlanPreview, upload.facingDirection]);

  useEffect(() => {
    generateOverlay();
  }, [generateOverlay]);

  const handleDownloadPdf = async () => {
    try {
      const res = await fetch('/api/report/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          questionnaire,
          facingDirection: upload.facingDirection,
          overlayImage,
        }),
      });
      
      if (res.ok) {
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `vastu-report-${questionnaire.name.replace(/\s+/g, '-')}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
    } catch (err) {
      console.error('PDF download failed:', err);
    }
  };

  const directionLabel = FACING_DIRECTIONS.find(d => d.value === upload.facingDirection)?.label || '';
  const problemAreaLabels = questionnaire.problemAreas.map(
    a => PROBLEM_AREAS.find(p => p.value === a)?.label || a
  );

  return (
    <div className="max-w-4xl mx-auto">
      {/* Report Header */}
      <div className="text-center mb-8">
        <h2 className="text-2xl font-bold text-gray-900">Your Vastu Report</h2>
        <p className="mt-2 text-gray-600">
          Generated for <strong>{questionnaire.name}</strong> &bull; {directionLabel} Facing Property
        </p>
      </div>

      {/* Report Content */}
      <div className="bg-white border border-gray-200 rounded-2xl shadow-lg overflow-hidden">
        
        {/* Report Title Section */}
        <div className="bg-gradient-to-r from-amber-500 to-orange-500 p-6 text-white">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-xl font-bold">Vastu Analysis Report</h3>
              <p className="text-amber-100 text-sm mt-1">Shilaavinyaas Vastu Consultancy</p>
            </div>
            <div className="text-right text-sm">
              <p className="text-amber-100">Generated on</p>
              <p className="font-medium">{new Date().toLocaleDateString('en-IN', { 
                day: 'numeric', month: 'long', year: 'numeric' 
              })}</p>
            </div>
          </div>
        </div>

        {/* Property Summary */}
        <div className="p-6 border-b border-gray-100">
          <h4 className="font-semibold text-gray-800 mb-3">Property Details</h4>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div>
              <p className="text-gray-500">Type</p>
              <p className="font-medium text-gray-800 capitalize">
                {questionnaire.propertyCategory} &mdash; {questionnaire.propertySubType.replace(/_/g, ' ')}
              </p>
            </div>
            <div>
              <p className="text-gray-500">Facing Direction</p>
              <p className="font-medium text-gray-800">{directionLabel}</p>
            </div>
            {questionnaire.sizeInSqFt && (
              <div>
                <p className="text-gray-500">Size</p>
                <p className="font-medium text-gray-800">{questionnaire.sizeInSqFt} Sq Ft</p>
              </div>
            )}
            <div>
              <p className="text-gray-500">Problem Areas</p>
              <p className="font-medium text-gray-800">{problemAreaLabels.join(', ')}</p>
            </div>
            {questionnaire.otherProblemText && (
              <div>
                <p className="text-gray-500">Other Concern</p>
                <p className="font-medium text-gray-800">{questionnaire.otherProblemText}</p>
              </div>
            )}
          </div>
        </div>

        {/* Chakra Overlay Image - THE MAIN DELIVERABLE */}
        <div className="p-6 border-b border-gray-100">
          <h4 className="font-semibold text-gray-800 mb-2">Floor Plan with Vastu Chakra Overlay</h4>
          <p className="text-sm text-gray-500 mb-4">
            The Vastu Chakra has been aligned with your {directionLabel} facing floor plan. 
            All directional zones are positioned accurately based on the specified orientation.
          </p>
          
          {isGenerating ? (
            <div className="flex items-center justify-center h-64 bg-gray-50 rounded-lg border border-gray-200">
              <div className="text-center">
                <div className="animate-spin w-10 h-10 border-3 border-amber-500 border-t-transparent rounded-full mx-auto mb-3"></div>
                <p className="text-gray-500 text-sm">Generating Vastu Chakra overlay...</p>
                <p className="text-gray-400 text-xs mt-1">Aligning directions with your floor plan</p>
              </div>
            </div>
          ) : error ? (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-600 text-sm">
              <p className="font-medium">Error:</p>
              <p>{error}</p>
            </div>
          ) : overlayImage ? (
            <div className="border border-gray-200 rounded-lg overflow-hidden bg-gray-50">
              <img
                src={overlayImage}
                alt="Floor plan with Vastu Chakra overlay aligned to facing direction"
                className="w-full h-auto max-h-[500px] object-contain"
              />
            </div>
          ) : null}
          
          <div className="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3">
            <p className="text-amber-800 text-xs leading-relaxed">
              <strong>How to read this overlay:</strong> The Vastu Chakra&apos;s North is correctly aligned with the true North 
              relative to your {directionLabel} facing property. The top of this image represents the {directionLabel} direction 
              (your property&apos;s facing side). All zones (Ishan, Agneya, Nairitya, Vayavya etc.) are mapped accordingly.
            </p>
          </div>
        </div>

        {/* Zone-wise Analysis Placeholder */}
        <div className="p-6 border-b border-gray-100">
          <h4 className="font-semibold text-gray-800 mb-3">Zone-wise Analysis</h4>
          <p className="text-sm text-gray-500 mb-4">
            Based on your floor plan orientation and the identified problem areas ({problemAreaLabels.join(', ')}).
          </p>
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-gray-500 text-sm">
            <p>Detailed zone-wise Vastu analysis based on the Chakra overlay mapping.</p>
            <p className="mt-2 text-xs text-gray-400">(Full analysis content will be generated by the report engine)</p>
          </div>
        </div>

        {/* ===== RECOMMENDATION SECTION - HIGHLIGHTED ===== */}
        <div className="p-6 bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 border-t-4 border-amber-500">
          <div className="flex items-center gap-3 mb-5">
            <div className="w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center flex-shrink-0">
              <span className="text-white text-lg">★</span>
            </div>
            <h4 className="font-bold text-gray-900 text-lg">Recommendation</h4>
          </div>
          
          <div className="space-y-4 text-gray-700 text-sm leading-relaxed">
            {RECOMMENDATION_TEXT.split('\n\n').map((paragraph, idx) => (
              <p key={idx}>{paragraph}</p>
            ))}
          </div>

          <div className="mt-6 bg-white border-2 border-amber-300 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4 shadow-sm">
            <div className="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0">
              <span className="text-2xl">📞</span>
            </div>
            <div className="flex-1">
              <p className="font-semibold text-gray-800">Book a Personalised Offline Consultation</p>
              <p className="text-sm text-gray-500 mt-1">
                Get an expert Vastu consultant to visit your property for a comprehensive, in-person assessment with personalised remedies.
              </p>
            </div>
            <a
              href="#"
              className="inline-block px-5 py-3 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition-colors shadow-md whitespace-nowrap"
            >
              Connect with Our Team
            </a>
          </div>
        </div>
      </div>

      {/* Action Buttons */}
      <div className="flex gap-4 mt-8">
        <button
          onClick={onBack}
          className="px-6 py-4 border-2 border-gray-200 text-gray-600 rounded-xl font-medium hover:bg-gray-50 transition-colors"
        >
          ← Back
        </button>
        <button
          onClick={handleDownloadPdf}
          disabled={!overlayImage}
          className="flex-1 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-semibold text-lg hover:from-amber-600 hover:to-orange-600 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Download PDF Report
        </button>
      </div>
    </div>
  );
}
