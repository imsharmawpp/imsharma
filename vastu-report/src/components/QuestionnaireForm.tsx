'use client';

import React, { useState } from 'react';
import { QuestionnaireData, PropertyCategory, PropertySubType, ProblemArea, OtpState } from '@/types';
import { COMMERCIAL_SUB_TYPES, RESIDENTIAL_SUB_TYPES, PROBLEM_AREAS, MAX_PROBLEM_AREAS, MAX_OTHER_PROBLEM_TEXT_LENGTH } from '@/lib/constants';

interface QuestionnaireFormProps {
  onComplete: (data: QuestionnaireData) => void;
}

export default function QuestionnaireForm({ onComplete }: QuestionnaireFormProps) {
  const [propertyCategory, setPropertyCategory] = useState<PropertyCategory | ''>('');
  const [propertySubType, setPropertySubType] = useState<PropertySubType | ''>('');
  const [sizeInSqFt, setSizeInSqFt] = useState<string>('');
  const [problemAreas, setProblemAreas] = useState<ProblemArea[]>([]);
  const [otherProblemText, setOtherProblemText] = useState<string>('');
  const [name, setName] = useState<string>('');
  const [mobileNumber, setMobileNumber] = useState<string>('');
  const [email, setEmail] = useState<string>('');
  const [otpState, setOtpState] = useState<OtpState>({ sent: false, verified: false, otp: '', timer: 0 });
  const [otpInput, setOtpInput] = useState<string>('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleCategoryChange = (category: PropertyCategory) => {
    setPropertyCategory(category);
    setPropertySubType('');
  };

  const handleProblemAreaToggle = (area: ProblemArea) => {
    setProblemAreas(prev => {
      if (prev.includes(area)) {
        return prev.filter(a => a !== area);
      }
      if (prev.length >= MAX_PROBLEM_AREAS) {
        return prev;
      }
      return [...prev, area];
    });
  };

  const handleSendOtp = async () => {
    if (!mobileNumber || mobileNumber.length < 10) {
      setErrors(prev => ({ ...prev, mobileNumber: 'Please enter a valid 10-digit mobile number' }));
      return;
    }
    setErrors(prev => ({ ...prev, mobileNumber: '' }));
    
    try {
      // API call to send OTP
      const res = await fetch('/api/otp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mobileNumber }),
      });
      
      if (res.ok) {
        setOtpState(prev => ({ ...prev, sent: true, timer: 60 }));
        // Start countdown timer
        const interval = setInterval(() => {
          setOtpState(prev => {
            if (prev.timer <= 1) {
              clearInterval(interval);
              return { ...prev, timer: 0 };
            }
            return { ...prev, timer: prev.timer - 1 };
          });
        }, 1000);
      }
    } catch {
      setErrors(prev => ({ ...prev, mobileNumber: 'Failed to send OTP. Please try again.' }));
    }
  };

  const handleVerifyOtp = async () => {
    try {
      const res = await fetch('/api/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mobileNumber, otp: otpInput }),
      });
      
      if (res.ok) {
        setOtpState(prev => ({ ...prev, verified: true }));
        setErrors(prev => ({ ...prev, otp: '' }));
      } else {
        setErrors(prev => ({ ...prev, otp: 'Invalid OTP. Please try again.' }));
      }
    } catch {
      setErrors(prev => ({ ...prev, otp: 'Verification failed. Please try again.' }));
    }
  };

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!propertyCategory) newErrors.propertyCategory = 'Please select property type';
    if (!propertySubType) newErrors.propertySubType = 'Please select property sub-type';
    if (problemAreas.length === 0) newErrors.problemAreas = 'Please select at least one problem area';
    if (problemAreas.includes('other') && !otherProblemText.trim()) {
      newErrors.otherProblemText = 'Please describe your issue';
    }
    if (!name.trim()) newErrors.name = 'Name is required';
    if (!mobileNumber || mobileNumber.length < 10) newErrors.mobileNumber = 'Valid mobile number is required';
    if (!otpState.verified) newErrors.otp = 'Please verify your mobile number with OTP';
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;
    
    const data: QuestionnaireData = {
      propertyCategory: propertyCategory as PropertyCategory,
      propertySubType: propertySubType as PropertySubType,
      sizeInSqFt: sizeInSqFt ? parseInt(sizeInSqFt) : undefined,
      problemAreas,
      otherProblemText: problemAreas.includes('other') ? otherProblemText : undefined,
      name: name.trim(),
      mobileNumber,
      email: email.trim() || undefined,
    };
    
    onComplete(data);
  };

  const subTypes = propertyCategory === 'commercial' 
    ? COMMERCIAL_SUB_TYPES 
    : propertyCategory === 'residential' 
      ? RESIDENTIAL_SUB_TYPES 
      : [];

  return (
    <form onSubmit={handleSubmit} className="max-w-2xl mx-auto space-y-8">
      {/* Header */}
      <div className="text-center">
        <h2 className="text-2xl font-bold text-gray-900">Property Details</h2>
        <p className="mt-2 text-gray-600">Tell us about your property to get a personalised Vastu report</p>
      </div>

      {/* Property Category */}
      <div className="space-y-3">
        <label className="block text-sm font-semibold text-gray-700">
          Property Type <span className="text-red-500">*</span>
        </label>
        <div className="grid grid-cols-2 gap-4">
          <button
            type="button"
            onClick={() => handleCategoryChange('commercial')}
            className={`p-4 rounded-xl border-2 text-center transition-all ${
              propertyCategory === 'commercial'
                ? 'border-amber-500 bg-amber-50 text-amber-700 shadow-md'
                : 'border-gray-200 hover:border-amber-300 text-gray-600'
            }`}
          >
            <div className="text-2xl mb-1">🏢</div>
            <div className="font-medium">Commercial</div>
          </button>
          <button
            type="button"
            onClick={() => handleCategoryChange('residential')}
            className={`p-4 rounded-xl border-2 text-center transition-all ${
              propertyCategory === 'residential'
                ? 'border-amber-500 bg-amber-50 text-amber-700 shadow-md'
                : 'border-gray-200 hover:border-amber-300 text-gray-600'
            }`}
          >
            <div className="text-2xl mb-1">🏠</div>
            <div className="font-medium">Residential</div>
          </button>
        </div>
        {errors.propertyCategory && <p className="text-red-500 text-sm">{errors.propertyCategory}</p>}
      </div>

      {/* Property Sub-Type */}
      {propertyCategory && (
        <div className="space-y-3">
          <label className="block text-sm font-semibold text-gray-700">
            {propertyCategory === 'commercial' ? 'Commercial' : 'Residential'} Type <span className="text-red-500">*</span>
          </label>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
            {subTypes.map((type) => (
              <button
                key={type.value}
                type="button"
                onClick={() => setPropertySubType(type.value)}
                className={`p-3 rounded-lg border-2 text-sm font-medium transition-all ${
                  propertySubType === type.value
                    ? 'border-amber-500 bg-amber-50 text-amber-700'
                    : 'border-gray-200 hover:border-amber-300 text-gray-600'
                }`}
              >
                {type.label}
              </button>
            ))}
          </div>
          {errors.propertySubType && <p className="text-red-500 text-sm">{errors.propertySubType}</p>}
        </div>
      )}

      {/* Size (Optional) */}
      {propertySubType && (
        <div className="space-y-2">
          <label className="block text-sm font-semibold text-gray-700">
            Size (in Sq Ft) <span className="text-gray-400 font-normal">— optional</span>
          </label>
          <input
            type="number"
            value={sizeInSqFt}
            onChange={(e) => setSizeInSqFt(e.target.value)}
            placeholder="e.g. 1200"
            className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors"
          />
        </div>
      )}

      {/* Problem Areas - Multi-select like LinkedIn skills */}
      <div className="space-y-3">
        <label className="block text-sm font-semibold text-gray-700">
          Problem Area <span className="text-red-500">*</span>
          <span className="text-gray-400 font-normal ml-2">(Select up to {MAX_PROBLEM_AREAS})</span>
        </label>
        <div className="flex flex-wrap gap-2">
          {PROBLEM_AREAS.map((area) => {
            const isSelected = problemAreas.includes(area.value);
            const isDisabled = !isSelected && problemAreas.length >= MAX_PROBLEM_AREAS;
            
            return (
              <button
                key={area.value}
                type="button"
                onClick={() => handleProblemAreaToggle(area.value)}
                disabled={isDisabled}
                className={`px-4 py-2 rounded-full border-2 text-sm font-medium transition-all ${
                  isSelected
                    ? 'border-amber-500 bg-amber-500 text-white shadow-md'
                    : isDisabled
                      ? 'border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed'
                      : 'border-gray-200 hover:border-amber-300 text-gray-600 hover:text-amber-600'
                }`}
              >
                {isSelected && <span className="mr-1">✓</span>}
                {area.label}
              </button>
            );
          })}
        </div>
        {errors.problemAreas && <p className="text-red-500 text-sm">{errors.problemAreas}</p>}
        
        {/* Other problem text input */}
        {problemAreas.includes('other') && (
          <div className="mt-3">
            <input
              type="text"
              value={otherProblemText}
              onChange={(e) => setOtherProblemText(e.target.value.slice(0, MAX_OTHER_PROBLEM_TEXT_LENGTH))}
              placeholder="Describe your issue (max 30 characters)"
              maxLength={MAX_OTHER_PROBLEM_TEXT_LENGTH}
              className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors"
            />
            <p className="text-xs text-gray-400 mt-1">{otherProblemText.length}/{MAX_OTHER_PROBLEM_TEXT_LENGTH} characters</p>
            {errors.otherProblemText && <p className="text-red-500 text-sm">{errors.otherProblemText}</p>}
          </div>
        )}
      </div>

      {/* Personal Details */}
      <div className="space-y-4 pt-4 border-t border-gray-100">
        <h3 className="text-lg font-semibold text-gray-800">Contact Details</h3>
        
        {/* Name */}
        <div className="space-y-2">
          <label className="block text-sm font-semibold text-gray-700">
            Name <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Enter your full name"
            className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors"
          />
          {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
        </div>

        {/* Mobile Number with OTP */}
        <div className="space-y-2">
          <label className="block text-sm font-semibold text-gray-700">
            Mobile Number (WhatsApp) <span className="text-red-500">*</span>
          </label>
          <div className="flex gap-2">
            <div className="flex items-center px-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-500 text-sm">
              +91
            </div>
            <input
              type="tel"
              value={mobileNumber}
              onChange={(e) => setMobileNumber(e.target.value.replace(/\D/g, '').slice(0, 10))}
              placeholder="10-digit mobile number"
              maxLength={10}
              disabled={otpState.verified}
              className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors disabled:bg-gray-50"
            />
            {!otpState.verified && (
              <button
                type="button"
                onClick={handleSendOtp}
                disabled={otpState.timer > 0 || mobileNumber.length < 10}
                className="px-4 py-3 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
              >
                {otpState.timer > 0 ? `Resend (${otpState.timer}s)` : otpState.sent ? 'Resend OTP' : 'Send OTP'}
              </button>
            )}
          </div>
          {errors.mobileNumber && <p className="text-red-500 text-sm">{errors.mobileNumber}</p>}
          
          {/* OTP Input */}
          {otpState.sent && !otpState.verified && (
            <div className="flex gap-2 mt-2">
              <input
                type="text"
                value={otpInput}
                onChange={(e) => setOtpInput(e.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="Enter 6-digit OTP"
                maxLength={6}
                className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors"
              />
              <button
                type="button"
                onClick={handleVerifyOtp}
                disabled={otpInput.length < 6}
                className="px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
              >
                Verify
              </button>
            </div>
          )}
          {otpState.verified && (
            <p className="text-green-600 text-sm font-medium flex items-center gap-1">
              <span>✓</span> Mobile number verified
            </p>
          )}
          {errors.otp && <p className="text-red-500 text-sm">{errors.otp}</p>}
        </div>

        {/* Email (Optional) */}
        <div className="space-y-2">
          <label className="block text-sm font-semibold text-gray-700">
            Email <span className="text-gray-400 font-normal">— optional</span>
          </label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="your@email.com"
            className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:ring-0 focus:outline-none transition-colors"
          />
        </div>
      </div>

      {/* Submit Button */}
      <div className="pt-4">
        <button
          type="submit"
          className="w-full py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-semibold text-lg hover:from-amber-600 hover:to-orange-600 transition-all shadow-lg hover:shadow-xl"
        >
          Continue to Upload Floor Plan →
        </button>
      </div>
    </form>
  );
}
