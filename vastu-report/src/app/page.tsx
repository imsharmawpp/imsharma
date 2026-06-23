'use client';

import React, { useState } from 'react';
import { QuestionnaireData, UploadData } from '@/types';
import QuestionnaireForm from '@/components/QuestionnaireForm';
import UploadAndDirection from '@/components/UploadAndDirection';
import ReportPreview from '@/components/ReportPreview';
import StepIndicator from '@/components/StepIndicator';

const STEPS = [
  { label: 'Details', description: 'Property info' },
  { label: 'Upload & Direction', description: 'Floor plan' },
  { label: 'Report', description: 'View results' },
];

export default function Home() {
  const [currentStep, setCurrentStep] = useState(0);
  const [questionnaireData, setQuestionnaireData] = useState<QuestionnaireData | null>(null);
  const [uploadData, setUploadData] = useState<UploadData | null>(null);

  const handleQuestionnaireComplete = async (data: QuestionnaireData) => {
    setQuestionnaireData(data);
    
    // Capture lead - send to CRM/CMS
    try {
      await fetch('/api/leads/capture', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
    } catch (error) {
      console.error('Lead capture failed:', error);
      // Don't block the user flow if lead capture fails (it's for internal tracking)
    }
    
    setCurrentStep(1);
  };

  const handleUploadComplete = (data: UploadData) => {
    // STRICT GATE: Only proceed if we have a valid floor plan with preview
    if (!data.floorPlanFile || !data.floorPlanPreview || !data.facingDirection) {
      console.error('Attempted to proceed without valid floor plan data');
      return;
    }
    
    setUploadData(data);
    // NOTE: In production, payment would happen here before showing the report.
    // Flow: Upload → Direction → Payment Gateway → Report
    setCurrentStep(2);
  };

  // STRICT: Report can only be shown if ALL required data is present
  const canShowReport = Boolean(
    questionnaireData && 
    uploadData && 
    uploadData.floorPlanPreview && 
    uploadData.floorPlanFile &&
    uploadData.facingDirection
  );

  return (
    <main className="min-h-screen bg-gradient-to-b from-amber-50/30 to-white">
      {/* Header */}
      <header className="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-lg flex items-center justify-center shadow-md">
              <span className="text-white font-bold text-lg">S</span>
            </div>
            <div>
              <h1 className="text-lg font-bold text-gray-900">Shilaavinyaas</h1>
              <p className="text-xs text-gray-500">Vastu Consultancy</p>
            </div>
          </div>
          <nav className="hidden md:flex items-center gap-6 text-sm text-gray-600">
            <a href="#" className="hover:text-amber-600 transition-colors font-medium">My Reports</a>
            <a href="#" className="hover:text-amber-600 transition-colors font-medium">Account</a>
            <a href="#" className="px-4 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 transition-colors shadow-sm">
              Contact Us
            </a>
          </nav>
        </div>
      </header>

      {/* Content */}
      <div className="max-w-6xl mx-auto px-4 py-8">
        {/* Step Indicator */}
        <StepIndicator steps={STEPS} currentStep={currentStep} />

        {/* Step Content */}
        <div className="mt-8">
          {currentStep === 0 && (
            <QuestionnaireForm onComplete={handleQuestionnaireComplete} />
          )}
          
          {currentStep === 1 && (
            <UploadAndDirection
              onComplete={handleUploadComplete}
              onBack={() => setCurrentStep(0)}
            />
          )}
          
          {currentStep === 2 && canShowReport && questionnaireData && uploadData && (
            <ReportPreview
              questionnaire={questionnaireData}
              upload={uploadData}
              onBack={() => setCurrentStep(1)}
            />
          )}

          {/* Fallback: If somehow step 2 is reached without valid data */}
          {currentStep === 2 && !canShowReport && (
            <div className="max-w-lg mx-auto text-center py-16">
              <div className="text-6xl mb-4">⚠️</div>
              <h3 className="text-xl font-bold text-gray-800 mb-2">Cannot Generate Report</h3>
              <p className="text-gray-500 mb-6">
                A valid floor plan is required to generate your Vastu report. 
                Please go back and upload a clear, digital floor plan.
              </p>
              <button
                onClick={() => setCurrentStep(1)}
                className="px-6 py-3 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 transition-colors"
              >
                ← Go Back to Upload
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Footer */}
      <footer className="bg-gray-900 text-gray-400 py-8 mt-16">
        <div className="max-w-6xl mx-auto px-4 text-center text-sm">
          <p className="text-gray-300">&copy; {new Date().getFullYear()} Shilaavinyaas Vastu Consultancy. All rights reserved.</p>
          <p className="mt-2 text-gray-500">
            Full-service Vastu consultancy for residential, commercial & industrial properties.
          </p>
        </div>
      </footer>
    </main>
  );
}
