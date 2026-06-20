'use client';

import React from 'react';

interface Step {
  label: string;
  description: string;
}

interface StepIndicatorProps {
  steps: Step[];
  currentStep: number;
}

export default function StepIndicator({ steps, currentStep }: StepIndicatorProps) {
  return (
    <div className="flex items-center justify-center mb-8">
      {steps.map((step, index) => (
        <React.Fragment key={index}>
          <div className="flex items-center">
            <div className={`flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold transition-all ${
              index < currentStep
                ? 'bg-green-500 text-white'
                : index === currentStep
                  ? 'bg-amber-500 text-white shadow-lg ring-4 ring-amber-100'
                  : 'bg-gray-200 text-gray-400'
            }`}>
              {index < currentStep ? '✓' : index + 1}
            </div>
            <div className="ml-3 hidden sm:block">
              <p className={`text-sm font-medium ${
                index <= currentStep ? 'text-gray-800' : 'text-gray-400'
              }`}>
                {step.label}
              </p>
              <p className="text-xs text-gray-400">{step.description}</p>
            </div>
          </div>
          {index < steps.length - 1 && (
            <div className={`w-12 md:w-20 h-0.5 mx-3 ${
              index < currentStep ? 'bg-green-500' : 'bg-gray-200'
            }`} />
          )}
        </React.Fragment>
      ))}
    </div>
  );
}
