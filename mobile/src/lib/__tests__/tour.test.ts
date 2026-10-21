import { resumeIndex, roundedRectPath, sameRect, tipPlacement, TOUR_STEPS, tourStepsFor } from '../tour';

describe('tourStepsFor', () => {
  it('shows every step to whoever can log', () => {
    expect(tourStepsFor(true)).toHaveLength(TOUR_STEPS.length);
  });

  it('leaves out the logging steps for read-only people', () => {
    const steps = tourStepsFor(false);

    expect(steps.map((step) => step.id)).not.toContain('mic');
    expect(steps[steps.length - 1].id).toBe('end');
  });

  it('points every step but the last at a target on a tab', () => {
    TOUR_STEPS.slice(0, -1).forEach((step) => {
      expect(step.route).not.toBeNull();
      expect(step.target).not.toBeNull();
    });
  });
});

describe('resumeIndex', () => {
  it('resumes where the person stopped', () => {
    expect(resumeIndex(4, 9)).toBe(4);
  });

  it('starts over when nothing was saved, the step is gone or it was the last one', () => {
    expect(resumeIndex(null, 9)).toBe(0);
    expect(resumeIndex(12, 9)).toBe(0);
    expect(resumeIndex(8, 9)).toBe(0);
  });
});

describe('tipPlacement', () => {
  const hole = { x: 100, y: 300, width: 170, height: 140 };

  it('goes below the highlight when it fits', () => {
    expect(tipPlacement(hole, 200, 844)).toEqual({ top: 456, side: 'below' });
  });

  it('goes above when there is no room below', () => {
    expect(tipPlacement({ ...hole, y: 600 }, 200, 844)).toEqual({ top: 384, side: 'above' });
  });

  it('never leaves the top of the screen', () => {
    expect(tipPlacement({ ...hole, y: 100, height: 700 }, 200, 844).top).toBe(16);
  });
});

describe('sameRect', () => {
  const rect = { x: 24, y: 120, width: 342, height: 90 };

  it('treats tiny rounding differences as the same place', () => {
    expect(sameRect(rect, { ...rect, y: 120.4 })).toBe(true);
  });

  it('notices when the element moved, as when the top margin comes in late', () => {
    expect(sameRect(rect, { ...rect, y: 144 })).toBe(false);
  });
});

describe('roundedRectPath', () => {
  it('draws a closed rounded rectangle', () => {
    const path = roundedRectPath({ x: 10, y: 20, width: 100, height: 50 }, 10);

    expect(path.startsWith('M20 20')).toBe(true);
    expect(path.endsWith('Z')).toBe(true);
  });

  it('keeps the corners inside a small box', () => {
    expect(roundedRectPath({ x: 0, y: 0, width: 8, height: 8 }, 10)).toContain('A4 4');
  });
});
